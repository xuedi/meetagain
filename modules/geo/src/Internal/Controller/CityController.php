<?php declare(strict_types=1);

namespace Module\Geo\Internal\Controller;

use Module\Geo\Contract\CityView;
use Module\Geo\Contract\DirectoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CityController extends AbstractController
{
    public const int MIN_QUERY_LENGTH = 2;

    public function __construct(
        private readonly DirectoryInterface $directory,
    ) {}

    #[Route('/geo/cities', name: 'app_geo_cities', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->query->getString('q'));
        $country = $request->query->getString('country');
        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return new JsonResponse([]);
        }

        $cities = $this->directory->searchCities($query, $country === '' ? null : $country, $request->getLocale());

        return new JsonResponse(array_map(static fn(CityView $city): array => [
            'id' => $city->id,
            'name' => $city->name,
            'country' => $city->countryIso2,
            'label' => $city->name . ', ' . $city->countryName,
        ], $cities));
    }
}
