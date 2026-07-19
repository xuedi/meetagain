<?php declare(strict_types=1);

namespace App\Controller;

use App\Enum\ItemViewType;
use App\Service\Item\ItemViewResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ItemController extends AbstractController
{
    public function __construct(
        private readonly ItemViewResolver $viewResolver,
    ) {}

    #[Route('/item/{itemType}/view/{mode}', name: 'app_item_set_view', methods: ['GET'])]
    public function setView(string $itemType, ItemViewType $mode, Request $request): RedirectResponse
    {
        $this->viewResolver->set($itemType, $mode);

        $referer = $request->headers->get('referer');
        if ($referer !== null && $referer !== '' && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            return $this->redirect($referer);
        }

        return $this->redirect('/');
    }
}
