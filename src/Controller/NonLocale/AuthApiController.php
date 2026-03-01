<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Entity\UserStatus;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/auth')]
class AuthApiController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'limiter.api_token')]
        private readonly RateLimiterFactory $apiTokenLimiter,
    ) {}

    #[Route('/token', name: 'app_api_auth_token', methods: ['POST'])]
    public function generateToken(Request $request): JsonResponse
    {
        $limiter = $this->apiTokenLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return new JsonResponse([
                'error' => 'Too many attempts. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode((string) $request->getContent(), true);
        $email = (string) ($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');

        try {
            return new JsonResponse(['token' => $this->authenticateWithToken($email, $password)]);
        } catch (AuthenticationException $error) {
            return new JsonResponse(['error' => $error->getMessage()], Response::HTTP_UNAUTHORIZED);
        }
    }

    #[Route('/token', name: 'app_api_auth_token_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function revokeToken(): JsonResponse
    {
        $user = $this->getAuthedUser();
        $user->setApiTokenHash(null);
        $user->setApiTokenCreatedAt(null);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function authenticateWithToken(string $email, string $password): string
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (
            !$user instanceof User
            || $user->getStatus() !== UserStatus::Active
            || !$this->passwordHasher->isPasswordValid($user, $password)
        ) {
            throw new AuthenticationException('Invalid credentials');
        }

        $plainToken = bin2hex(random_bytes(32));
        $user->setApiTokenHash(hash('sha256', $plainToken));
        $user->setApiTokenCreatedAt(new DateTimeImmutable());
        $this->em->flush();

        return $plainToken;
    }
}
