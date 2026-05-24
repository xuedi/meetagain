<?php declare(strict_types=1);

namespace Plugin\Ranking\ValueObject;

use Plugin\Ranking\Enum\Archetype;

final class ArchetypePresets
{
    /** @var array<string, RankPreset>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, RankPreset>
     */
    public static function all(): array
    {
        return self::$cache ??= self::build();
    }

    /**
     * @return list<RankPreset>
     */
    public static function forArchetype(Archetype $archetype): array
    {
        return array_values(array_filter(self::all(), static fn(RankPreset $p) => $p->archetype === $archetype));
    }

    public static function get(string $key): ?RankPreset
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return array<string, RankPreset>
     */
    private static function build(): array
    {
        $presets = [];

        $kyuDan = [];
        for ($n = 30; $n >= 1; $n--) {
            $kyuDan[] = new RankPresetEntry(label: sprintf('%d-kyu', $n));
        }
        for ($n = 1; $n <= 9; $n++) {
            $kyuDan[] = new RankPresetEntry(label: sprintf('%d-dan', $n));
        }
        $presets['go-kyu-dan'] = new RankPreset('go-kyu-dan', Archetype::KyuDan, 'ranking.preset.go-kyu-dan', $kyuDan);

        $presets['cefr'] = new RankPreset('cefr', Archetype::KyuDan, 'ranking.preset.cefr', [
            new RankPresetEntry('A1'),
            new RankPresetEntry('A2'),
            new RankPresetEntry('B1'),
            new RankPresetEntry('B2'),
            new RankPresetEntry('C1'),
            new RankPresetEntry('C2'),
        ]);

        $abrsm = [];
        for ($n = 1; $n <= 8; $n++) {
            $abrsm[] = new RankPresetEntry(sprintf('Grade %d', $n));
        }
        $abrsm[] = new RankPresetEntry('Diploma');
        $presets['abrsm-music'] = new RankPreset('abrsm-music', Archetype::KyuDan, 'ranking.preset.abrsm-music', $abrsm);

        $presets['scout-bsa'] = new RankPreset('scout-bsa', Archetype::KyuDan, 'ranking.preset.scout-bsa', [
            new RankPresetEntry('Scout'),
            new RankPresetEntry('Tenderfoot'),
            new RankPresetEntry('Second Class'),
            new RankPresetEntry('First Class'),
            new RankPresetEntry('Star'),
            new RankPresetEntry('Life'),
            new RankPresetEntry('Eagle'),
        ]);

        $presets['padi-dive'] = new RankPreset('padi-dive', Archetype::KyuDan, 'ranking.preset.padi-dive', [
            new RankPresetEntry('Open Water'),
            new RankPresetEntry('Advanced Open Water'),
            new RankPresetEntry('Rescue Diver'),
            new RankPresetEntry('Divemaster'),
            new RankPresetEntry('Instructor'),
        ]);

        $presets['chess-titles'] = new RankPreset('chess-titles', Archetype::KyuDan, 'ranking.preset.chess-titles', [
            new RankPresetEntry('Candidate Master'),
            new RankPresetEntry('FIDE Master'),
            new RankPresetEntry('International Master'),
            new RankPresetEntry('Grandmaster'),
        ]);

        $presets['toastmasters'] = new RankPreset('toastmasters', Archetype::KyuDan, 'ranking.preset.toastmasters', [
            new RankPresetEntry('Competent Communicator'),
            new RankPresetEntry('Advanced Communicator Bronze'),
            new RankPresetEntry('Advanced Communicator Silver'),
            new RankPresetEntry('Advanced Communicator Gold'),
            new RankPresetEntry('Distinguished Toastmaster'),
        ]);

        $presets['karate-belts'] = new RankPreset('karate-belts', Archetype::Belt, 'ranking.preset.karate-belts', [
            new RankPresetEntry('White Belt', '#ffffff', 'ranking.belt_color.white'),
            new RankPresetEntry('Yellow Belt', '#f6d000', 'ranking.belt_color.yellow'),
            new RankPresetEntry('Orange Belt', '#f59300', 'ranking.belt_color.orange'),
            new RankPresetEntry('Green Belt', '#1f8a2f', 'ranking.belt_color.green'),
            new RankPresetEntry('Blue Belt', '#2058a8', 'ranking.belt_color.blue'),
            new RankPresetEntry('Brown Belt', '#6b3a1a', 'ranking.belt_color.brown'),
            new RankPresetEntry('Black Belt', '#111111', 'ranking.belt_color.black'),
        ]);

        $presets['bjj-adult'] = new RankPreset('bjj-adult', Archetype::Belt, 'ranking.preset.bjj-adult', [
            new RankPresetEntry('White Belt', '#ffffff', 'ranking.belt_color.white'),
            new RankPresetEntry('Blue Belt', '#1a4c8c', 'ranking.belt_color.blue'),
            new RankPresetEntry('Purple Belt', '#3b1c5a', 'ranking.belt_color.purple'),
            new RankPresetEntry('Brown Belt', '#5a3214', 'ranking.belt_color.brown'),
            new RankPresetEntry('Black Belt', '#111111', 'ranking.belt_color.black'),
        ]);

        $presets['judo-adult'] = new RankPreset('judo-adult', Archetype::Belt, 'ranking.preset.judo-adult', [
            new RankPresetEntry('White Belt', '#ffffff', 'ranking.belt_color.white'),
            new RankPresetEntry('Yellow Belt', '#f6d000', 'ranking.belt_color.yellow'),
            new RankPresetEntry('Orange Belt', '#f59300', 'ranking.belt_color.orange'),
            new RankPresetEntry('Green Belt', '#1f8a2f', 'ranking.belt_color.green'),
            new RankPresetEntry('Blue Belt', '#1a4c8c', 'ranking.belt_color.blue'),
            new RankPresetEntry('Brown Belt', '#5a3214', 'ranking.belt_color.brown'),
            new RankPresetEntry('Black Belt', '#111111', 'ranking.belt_color.black'),
        ]);

        $presets['taekwondo'] = new RankPreset('taekwondo', Archetype::Belt, 'ranking.preset.taekwondo', [
            new RankPresetEntry('White Belt', '#ffffff', 'ranking.belt_color.white'),
            new RankPresetEntry('Yellow Belt', '#f6d000', 'ranking.belt_color.yellow'),
            new RankPresetEntry('Green Belt', '#1f8a2f', 'ranking.belt_color.green'),
            new RankPresetEntry('Blue Belt', '#1a4c8c', 'ranking.belt_color.blue'),
            new RankPresetEntry('Red Belt', '#c1272d', 'ranking.belt_color.red'),
            new RankPresetEntry('Black Belt', '#111111', 'ranking.belt_color.black'),
        ]);

        $presets['divisions-4'] = new RankPreset('divisions-4', Archetype::Division, 'ranking.preset.divisions-4', [
            new RankPresetEntry('Division 1'),
            new RankPresetEntry('Division 2'),
            new RankPresetEntry('Division 3'),
            new RankPresetEntry('Division 4'),
        ]);

        $presets['esports-tiers'] = new RankPreset('esports-tiers', Archetype::Division, 'ranking.preset.esports-tiers', [
            new RankPresetEntry('Bronze'),
            new RankPresetEntry('Silver'),
            new RankPresetEntry('Gold'),
            new RankPresetEntry('Platinum'),
            new RankPresetEntry('Diamond'),
            new RankPresetEntry('Master'),
            new RankPresetEntry('Grandmaster'),
        ]);

        return $presets;
    }
}
