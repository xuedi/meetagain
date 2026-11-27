<?php declare(strict_types=1);

namespace App\Controller\Admin\Support;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Service\Member\DeletionFeedbackService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/support/deletion-feedback')]
#[IsGranted('ROLE_ADMIN')]
final class DeletionFeedbackController extends AbstractSupportController implements AdminNavigationInterface, AdminTabsInterface
{
    private const int TOP_REASONS = 3;

    public function __construct(
        TranslatorInterface $translator,
        private readonly DeletionFeedbackService $deletionFeedback,
    ) {
        parent::__construct($translator, 'deletion_feedback');
    }

    #[Route('', name: 'app_admin_support_deletion_feedback', methods: ['GET'])]
    public function list(): Response
    {
        $feedback = $this->deletionFeedback->findNewestFirst();

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($feedback),
                $this->escape($this->translator->trans('admin_support_deletion_feedback.summary_total')),
            )),
        ];
        foreach (array_slice($this->deletionFeedback->countByReason(), 0, self::TOP_REASONS) as $row) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-light is-medium">%s&nbsp;<strong>%d</strong></span>',
                $this->escape($this->translator->trans($row['reason']->label())),
                $row['total'],
            ));
        }

        return $this->render('admin/support/deletion_feedback.html.twig', [
            'active' => 'support',
            'feedback' => $feedback,
            'adminTop' => new AdminTop(info: $info),
            'adminTabs' => $this->getTabs(),
        ]);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
