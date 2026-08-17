<?php declare(strict_types=1);

namespace App\Controller;

use App\Enum\ItemViewType;
use App\Item\ListRegistry;
use App\Item\Tag\FacetService;
use App\Service\Item\ViewResolver;
use App\Twig\ItemListExtension;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ItemController extends AbstractController
{
    public function __construct(
        private readonly ViewResolver $viewResolver,
        private readonly ListRegistry $listRegistry,
        private readonly FacetService $facetService,
    ) {}

    #[Route('/item/{itemType}/view/{mode}', name: 'app_item_set_view', methods: ['GET'])]
    public function setView(string $itemType, ItemViewType $mode, Request $request): RedirectResponse
    {
        if (!in_array($mode, ItemViewType::switchable(), true)) {
            throw $this->createNotFoundException();
        }

        $this->viewResolver->set($itemType, $mode);

        $referer = $request->headers->get('referer');
        if ($referer !== null && $referer !== '' && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            return $this->redirect($referer);
        }

        return $this->redirect('/');
    }

    #[Route('/item/{itemType}/fragment', name: 'app_item_list_fragment', methods: ['GET'])]
    public function listFragment(string $itemType, Request $request): Response
    {
        $provider = $this->listRegistry->providerFor($itemType);
        if ($provider === null) {
            throw $this->createNotFoundException();
        }

        $listUrl = $this->generateUrl($provider->getListRoute());
        if (!$request->isXmlHttpRequest()) {
            return $this->redirect($listUrl);
        }

        $mode = ItemViewType::tryFrom($request->query->getString('mode'));
        if ($mode !== null && in_array($mode, ItemViewType::switchable(), true)) {
            $this->viewResolver->set($itemType, $mode);
        }

        $response = new JsonResponse([
            'filter' => $this->renderView('_components/item/tag_filter.html.twig', ['itemType' => $itemType]),
            'body' => $this->renderView(ItemListExtension::BODY_TEMPLATE, ['itemType' => $itemType]),
            'url' => $this->facetService->urlFor($listUrl, $this->facetService->current()),
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
