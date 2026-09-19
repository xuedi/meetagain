<?php declare(strict_types=1);

namespace App\Form;

use App\Enum\SecurityEventType;
use App\Enum\SecurityMeasure;
use App\Service\Security\CaptchaService;
use App\Service\Security\ChallengeSigner;
use App\Service\Security\MeasureLogger;
use App\Service\Security\MeasureSettings;
use App\Service\Security\SecurityService;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

final class HumanCheckType extends AbstractType
{
    public const string HONEYPOT_FIELD = 'reference';

    public function __construct(
        private readonly MeasureSettings $measureSettings,
        private readonly CaptchaService $captchaService,
        private readonly ChallengeSigner $signer,
        private readonly MeasureLogger $measureLogger,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly SecurityService $securityService,
        private readonly int $humanCheckMinElapsedMs = 2000,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $context = (string) $options['context'];

        if ($this->isEnabled(SecurityMeasure::ImageCaptcha)) {
            $builder->add('captcha', TextType::class, [
                'label' => 'security.label_captcha_input',
                'constraints' => [new NotBlank()],
            ]);
        }

        if ($this->isEnabled(SecurityMeasure::Honeypot)) {
            $builder->add(self::HONEYPOT_FIELD, TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
            ]);
        }

        if ($this->needsStamp()) {
            $builder->add('stamp', HiddenType::class, ['required' => false]);
        }

        if ($this->isEnabled(SecurityMeasure::ProofOfWork)) {
            $builder->add('proof', HiddenType::class, ['required' => false]);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($context): void {
            $this->validateMeasures($event->getForm(), $context);
        });
    }

    #[Override]
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $context = (string) $options['context'];
        $difficulty = $this->measureSettings->proofOfWorkDifficulty();

        if (isset($view->children['stamp'])) {
            $view->children['stamp']->vars['value'] = $this->signer->issue($context, $difficulty);
        }

        if (isset($view->children['proof'])) {
            $view->children['proof']->vars['value'] = '';
        }

        $captchaImage = null;
        if (isset($view->children['captcha'])) {
            $captchaImage = $this->captchaService->generate($context);
        }

        $view->vars['context'] = $context;
        $view->vars['captchaImage'] = $captchaImage;
        $view->vars['powDifficulty'] = $difficulty;
        $view->vars['powEnabled'] = $this->isEnabled(SecurityMeasure::ProofOfWork);
        $view->vars['refreshCount'] = $this->captchaService->getRefreshCount();
        $view->vars['refreshTime'] = $this->captchaService->getRefreshTime();
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'mapped' => false,
            'label' => false,
            'error_bubbling' => false,
        ]);
        $resolver->setRequired('context');
        $resolver->setAllowedTypes('context', 'string');
    }

    private function validateMeasures(FormInterface $form, string $context): void
    {
        $request = $this->requestStack->getCurrentRequest();
        [$stamp, $stampReason] = $this->readStamp($form, $context);
        $genericErrorAdded = false;
        $reasons = [];

        foreach (SecurityMeasure::formMeasures() as $measure) {
            if (!$this->isEnabled($measure)) {
                continue;
            }

            $detail = match ($measure) {
                SecurityMeasure::ImageCaptcha => $this->checkCaptcha($form, $context),
                SecurityMeasure::Honeypot => $this->checkHoneypot($form),
                SecurityMeasure::SubmitTiming => $this->checkTiming($stamp, $stampReason),
                SecurityMeasure::ProofOfWork => $this->checkProofOfWork($form, $stamp, $stampReason),
            };

            if ($detail === null) {
                $this->measureLogger->recordPass($measure, $context);
                continue;
            }

            $this->measureLogger->recordBlock($measure, $context, $request, $detail);
            $reasons[] = (string) ($detail['reason'] ?? $measure->value);

            if ($measure === SecurityMeasure::ImageCaptcha || $genericErrorAdded) {
                continue;
            }

            $form->addError(new FormError($this->translator->trans('security.human_check_failed')));
            $genericErrorAdded = true;
        }

        if ($reasons === [] || $request === null || $this->isCrossSite($request)) {
            return;
        }

        $this->securityService->event(SecurityEventType::FormMeasure, $request, [
            'context' => $context,
            'reasons' => array_values(array_unique($reasons)),
        ]);
    }

    private function isCrossSite(Request $request): bool
    {
        $origin = $request->headers->get('Origin');
        $fetchSite = $request->headers->get('Sec-Fetch-Site');
        $foreignOrigin = $origin !== null && $origin !== $request->getSchemeAndHttpHost();
        $foreignFetch = $fetchSite !== null && !in_array($fetchSite, ['same-origin', 'none'], true);

        return $foreignOrigin || $foreignFetch;
    }

    /**
     * @return array{0: array{nonce: string, issuedAt: int, difficulty: int}|null, 1: string|null}
     */
    private function readStamp(FormInterface $form, string $context): array
    {
        if (!$form->has('stamp')) {
            return [null, null];
        }

        $submitted = (string) $form->get('stamp')->getData();
        if ($submitted === '') {
            return [null, 'missing_stamp'];
        }

        $stamp = $this->signer->verify($submitted, $context);
        if ($stamp === null) {
            return [null, $this->signer->rejectionReason($submitted, $context)];
        }

        if (!$this->signer->burn($stamp['nonce'])) {
            return [null, 'nonce_reused'];
        }

        return [$stamp, null];
    }

    /**
     * @return array<string, scalar|null>|null
     */
    private function checkCaptcha(FormInterface $form, string $context): ?array
    {
        $error = $this->captchaService->isValid($context, (string) $form->get('captcha')->getData());
        if ($error === null) {
            return null;
        }

        $form->get('captcha')->addError(new FormError($this->translator->trans($error)));

        return ['reason' => 'wrong_code'];
    }

    /**
     * @return array<string, scalar|null>|null
     */
    private function checkHoneypot(FormInterface $form): ?array
    {
        $value = (string) $form->get(self::HONEYPOT_FIELD)->getData();

        return $value === '' ? null : ['reason' => 'filled'];
    }

    /**
     * @param array{nonce: string, issuedAt: int, difficulty: int}|null $stamp
     *
     * @return array<string, scalar|null>|null
     */
    private function checkTiming(?array $stamp, ?string $stampReason): ?array
    {
        if ($stamp === null) {
            return ['reason' => $stampReason ?? 'missing_stamp'];
        }

        $elapsedMs = $this->signer->nowMs() - $stamp['issuedAt'];
        if ($elapsedMs >= $this->humanCheckMinElapsedMs) {
            return null;
        }

        return ['reason' => 'too_fast', 'elapsed_ms' => $elapsedMs];
    }

    /**
     * @param array{nonce: string, issuedAt: int, difficulty: int}|null $stamp
     *
     * @return array<string, scalar|null>|null
     */
    private function checkProofOfWork(FormInterface $form, ?array $stamp, ?string $stampReason): ?array
    {
        if ($stamp === null) {
            return ['reason' => $stampReason ?? 'missing_stamp'];
        }

        $difficulty = $this->measureSettings->proofOfWorkDifficulty();

        $proof = (string) $form->get('proof')->getData();
        if ($proof === '') {
            return ['reason' => 'missing_proof', 'difficulty' => $difficulty];
        }

        if (!$this->signer->isProofValid($stamp['nonce'], $proof, $difficulty)) {
            return ['reason' => 'invalid_proof', 'difficulty' => $difficulty];
        }

        return null;
    }

    private function needsStamp(): bool
    {
        return $this->isEnabled(SecurityMeasure::SubmitTiming) || $this->isEnabled(SecurityMeasure::ProofOfWork);
    }

    private function isEnabled(SecurityMeasure $measure): bool
    {
        return $this->measureSettings->isEnabled($measure);
    }
}
