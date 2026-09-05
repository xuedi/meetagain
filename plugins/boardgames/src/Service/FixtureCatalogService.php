<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

readonly class FixtureCatalogService
{
    /**
     * @return list<array{slug: string, parent: string|null, labels: array<string, string>}>
     */
    public function vocabulary(): array
    {
        return [
            $this->tag('mechanics', null, 'Mechanics', 'Mechaniken', '机制', 'Mecaniques', 'Mecanicas'),
            $this->tag('categories', null, 'Categories', 'Kategorien', '类别', 'Categories', 'Categorias'),

            $this->tag('deck-building', 'mechanics', 'Deck building', 'Deckbau', '构筑牌组', 'Construction de deck', 'Construccion de mazos'),
            $this->tag('worker-placement', 'mechanics', 'Worker placement', 'Arbeitereinsatz', '工人放置', "Placement d'ouvriers", 'Colocacion de trabajadores'),
            $this->tag('tile-laying', 'mechanics', 'Tile laying', 'Plättchenlegen', '版图拼放', 'Pose de tuiles', 'Colocacion de losetas'),
            $this->tag('area-control', 'mechanics', 'Area control', 'Gebietskontrolle', '区域控制', 'Controle de zone', 'Control de area'),
            $this->tag('cooperative', 'mechanics', 'Cooperative', 'Kooperativ', '合作', 'Cooperatif', 'Cooperativo'),
            $this->tag('trading', 'mechanics', 'Trading', 'Handel', '交易', 'Commerce', 'Comercio'),
            $this->tag('hidden-roles', 'mechanics', 'Hidden roles', 'Verdeckte Rollen', '隐藏身份', 'Roles caches', 'Roles ocultos'),

            $this->tag('party', 'categories', 'Party game', 'Partyspiel', '聚会游戏', "Jeu d'ambiance", 'Juego de fiesta'),
            $this->tag('strategy', 'categories', 'Strategy', 'Strategie', '策略', 'Strategie', 'Estrategia'),
            $this->tag('family', 'categories', 'Family', 'Familie', '家庭', 'Famille', 'Familiar'),
            $this->tag('abstract', 'categories', 'Abstract', 'Abstrakt', '抽象', 'Abstrait', 'Abstracto'),
            $this->tag('wordgame', 'categories', 'Word game', 'Wortspiel', '文字游戏', 'Jeu de mots', 'Juego de palabras'),
        ];
    }

    /**
     * @return list<array{name: string, year: int|null, minPlayers: int, maxPlayers: int, minPlaytime: int, maxPlaytime: int, weight: string, description: string, tags: list<string>, box: string}>
     */
    public function games(): array
    {
        return [
            $this->game('Catan', 1995, 3, 4, 60, 120, '2.30', 'Settlers trade and build on an island whose resources never quite line up.', ['trading', 'strategy'], 'catan-1995.jpg'),
            $this->game('Carcassonne', 2000, 2, 5, 30, 45, '1.90', 'Players lay tiles to build a medieval landscape and claim its features.', ['tile-laying', 'family'], 'carcassonne-2000.jpg'),
            $this->game('Ticket to Ride', 2004, 2, 5, 30, 60, '1.80', 'Collect train cards and claim railway routes across a continent.', ['family', 'strategy'], 'ticket-to-ride-2004.jpg'),
            $this->game('Pandemic', 2008, 2, 4, 45, 45, '2.40', 'A team of specialists races four diseases around the world.', ['cooperative', 'strategy'], 'pandemic-2008.jpg'),
            $this->game('7 Wonders', 2010, 3, 7, 30, 30, '2.30', 'Three ages of card drafting build a wonder of the ancient world.', ['strategy'], '7-wonders-2010.jpg'),
            $this->game('Dominion', 2008, 2, 4, 30, 30, '2.40', 'Every player builds a deck from a shared supply of kingdom cards.', ['deck-building', 'strategy'], 'dominion-2008.jpg'),
            $this->game('Azul', 2017, 2, 4, 30, 45, '1.80', 'Tile drafting for the walls of the Royal Palace of Evora.', ['abstract', 'family'], 'azul-2017.jpg'),
            $this->game('Wingspan', 2019, 1, 5, 40, 70, '2.40', 'A bird collection engine builder set in forest, grassland and wetland.', ['strategy'], 'wingspan-2019.jpg'),
            $this->game('Codenames', 2015, 2, 8, 15, 15, '1.30', 'Two spymasters give one-word clues to contact their agents first.', ['party', 'wordgame'], 'codenames-2015.jpg'),
            $this->game('Halma', 1883, 2, 4, 20, 30, '1.40', 'Pieces hop across the board in chains to reach the opposite camp first.', ['abstract', 'strategy'], 'halma-1883.jpg'),
            $this->game('Crokinole', 1876, 2, 4, 20, 30, '1.20', 'Wooden discs are flicked at the centre hole past a ring of pegs.', ['family', 'abstract'], 'crokinole-1876.jpg'),
            $this->game('Scythe', 2016, 1, 5, 90, 115, '3.40', 'Five factions compete for a war-torn 1920s Eastern Europe.', ['area-control', 'strategy'], 'scythe-2016.jpg'),
            $this->game('Terraforming Mars', 2016, 1, 5, 120, 120, '3.20', 'Corporations raise the temperature, oxygen and oceans of Mars.', ['strategy'], 'terraforming-mars-2016.jpg'),
            $this->game('Agricola', 2007, 1, 5, 30, 150, '3.60', 'A farming family expands its house, fields and pastures.', ['worker-placement', 'strategy'], 'agricola-2007.jpg'),
            $this->game('Everdell', 2018, 1, 4, 40, 80, '2.80', 'Woodland critters build a city of cards through the four seasons.', ['worker-placement', 'strategy'], 'everdell-2018.jpg'),
            $this->game('The Werewolves of Millers Hollow', 2001, 8, 18, 30, 30, '1.20', 'The village votes someone out each morning while the werewolves hunt each night.', ['hidden-roles', 'party'], 'werewolves-millers-hollow-2001.jpg'),
            $this->game('Just One', 2018, 3, 7, 20, 20, '1.10', 'Everyone writes a one-word clue, and matching clues cancel out.', ['party', 'wordgame'], 'just-one-2018.jpg'),
            $this->game('Backgammon', null, 2, 2, 20, 40, '1.80', 'Two players race their fifteen checkers home across twenty-four points.', ['abstract', 'strategy'], 'backgammon.jpg'),
            $this->game('Hive', 2001, 2, 2, 20, 20, '2.30', 'Insect tiles surround the opposing queen bee on an open board.', ['abstract'], 'hive-2001.jpg'),
            $this->game('Spirit Island', 2017, 1, 4, 90, 120, '4.00', 'Island spirits push back colonial invaders before the island is spoiled.', ['cooperative', 'strategy'], 'spirit-island-2017.jpg'),
        ];
    }

    /** @return array{slug: string, parent: string|null, labels: array<string, string>} */
    private function tag(string $slug, ?string $parent, string $en, string $de, string $zh, string $fr, string $es): array
    {
        return [
            'slug' => $slug,
            'parent' => $parent,
            'labels' => ['en' => $en, 'de' => $de, 'zh' => $zh, 'fr' => $fr, 'es' => $es],
        ];
    }

    /**
     * @param list<string> $tags
     *
     * @return array{name: string, year: int|null, minPlayers: int, maxPlayers: int, minPlaytime: int, maxPlaytime: int, weight: string, description: string, tags: list<string>, box: string}
     */
    private function game(
        string $name,
        ?int $year,
        int $minPlayers,
        int $maxPlayers,
        int $minPlaytime,
        int $maxPlaytime,
        string $weight,
        string $description,
        array $tags,
        string $box,
    ): array {
        return [
            'name' => $name,
            'year' => $year,
            'minPlayers' => $minPlayers,
            'maxPlayers' => $maxPlayers,
            'minPlaytime' => $minPlaytime,
            'maxPlaytime' => $maxPlaytime,
            'weight' => $weight,
            'description' => $description,
            'tags' => $tags,
            'box' => $box,
        ];
    }
}
