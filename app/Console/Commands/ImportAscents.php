<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\User;
use App\Models\Walk;
use App\Support\AscentImporter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * @phpstan-import-type PlannedWalk from AscentImporter
 */
#[Signature('ascents:import {file? : The munrotab workbook}
    {--climber=* : A ClimberID and the name of their account, like 1="Jo Bloggs"}
    {--group=* : A group code in the workbook and the name of the group, like MGB="Hill Friends"}
    {--add=* : Add a person to a group walk, like "MGB 2025-06-26=Jo Bloggs"}
    {--dry-run : Show what would be added and change nothing}
    {--rollback : Remove every walk this command made}')]
#[Description('Load past ascents from the munrotab workbook as completed walks (spec §5)')]
class ImportAscents extends Command
{
    public function handle(AscentImporter $importer): int
    {
        if ($this->option('rollback')) {
            return $this->rollback($importer);
        }

        try {
            $walks = $this->plan($importer);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->report($importer, $walks);

        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing was changed.');

            return self::SUCCESS;
        }

        $result = $importer->run($walks);
        $this->info("Added {$result['walks']} walks. Skipped {$result['skipped']} already imported.");

        return self::SUCCESS;
    }

    private function rollback(AscentImporter $importer): int
    {
        if ($this->option('dry-run')) {
            $this->info(Walk::where('notes', AscentImporter::MARKER)->count().' imported walks would be removed. Nothing was changed.');

            return self::SUCCESS;
        }

        $this->info($importer->rollback().' imported walks removed.');

        return self::SUCCESS;
    }

    /**
     * @return list<PlannedWalk>
     */
    private function plan(AscentImporter $importer): array
    {
        $file = (string) $this->argument('file');

        if ($file === '' || ! is_file($file)) {
            throw new InvalidArgumentException('Give the path of the munrotab workbook.');
        }

        $book = IOFactory::load($file);
        $munros = $book->getSheetByName('Munros') ?? throw new InvalidArgumentException('The workbook has no Munros sheet.');
        $climbed = $book->getSheetByName('Climbed') ?? throw new InvalidArgumentException('The workbook has no Climbed sheet.');

        $climbers = [];
        foreach ($this->pairs('climber') as $id => $name) {
            $climbers[(int) $id] = $this->account($name);
        }

        $groups = [];
        foreach ($this->pairs('group') as $code => $name) {
            $groups[$code] = $this->group($name);
        }

        return $importer->plan($this->climbedRows($climbed), $this->runningNumbers($munros), $climbers, $groups, $this->extras());
    }

    /**
     * @return array<int, int> running number => DoBIH number
     */
    private function runningNumbers(Worksheet $sheet): array
    {
        $map = [];

        foreach ($this->rows($sheet) as $row) {
            if (filled($row['RunningNo'] ?? null) && filled($row['DoBIH Number'] ?? null)) {
                $map[(int) $row['RunningNo']] = (int) $row['DoBIH Number'];
            }
        }

        return $map;
    }

    /**
     * @return list<array{running_no: int, climber: int, date: string, count: int, group: string}>
     */
    private function climbedRows(Worksheet $sheet): array
    {
        $rows = [];

        foreach ($this->rows($sheet) as $row) {
            if (blank($row['RunningNo'] ?? null)) {
                continue;
            }

            $date = $row['Date Climbed'] ?? null;

            if (blank($date)) {
                throw new InvalidArgumentException("Running number {$row['RunningNo']} has no date.");
            }

            $rows[] = [
                'running_no' => (int) $row['RunningNo'],
                'climber' => (int) $row['ClimberID'],
                'date' => is_numeric($date)
                    ? Date::excelToDateTimeObject((float) $date)->format('Y-m-d')
                    : CarbonImmutable::parse((string) $date)->toDateString(),
                'count' => max(1, (int) ($row['Climb Count'] ?? 1)),
                'group' => trim((string) $row['Group']),
            ];
        }

        return $rows;
    }

    /**
     * The sheet's rows keyed by its header names.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(Worksheet $sheet): array
    {
        $all = $sheet->toArray(null, true, false, false);
        $header = array_shift($all);

        return array_values(array_map(fn (array $row) => array_combine($header, array_slice($row, 0, count($header))), $all));
    }

    /**
     * @return list<array{group: string, date: string, user: User}>
     */
    private function extras(): array
    {
        $extras = [];

        foreach ((array) $this->option('add') as $spec) {
            if (! preg_match('/^(\S+) (\d{4}-\d{2}-\d{2})=(.+)$/', (string) $spec, $match)) {
                throw new InvalidArgumentException("Can't read --add \"{$spec}\". Use \"MGB 2025-06-26=Jo Bloggs\".");
            }

            $extras[] = ['group' => $match[1], 'date' => $match[2], 'user' => $this->account($match[3])];
        }

        return $extras;
    }

    /**
     * @return array<string, string>
     */
    private function pairs(string $option): array
    {
        $pairs = [];

        foreach ((array) $this->option($option) as $spec) {
            if (! str_contains((string) $spec, '=')) {
                throw new InvalidArgumentException("Can't read --{$option} \"{$spec}\". Use key=\"Name\".");
            }

            [$key, $name] = explode('=', (string) $spec, 2);
            $pairs[trim($key)] = trim($name, " \"'");
        }

        return $pairs;
    }

    private function account(string $name): User
    {
        $matches = User::where('name', $name)->get();

        if ($matches->count() !== 1) {
            throw new InvalidArgumentException("{$matches->count()} accounts are named \"{$name}\"; there must be exactly one.");
        }

        return $matches->first();
    }

    private function group(string $name): Group
    {
        $matches = Group::where('name', $name)->get();

        if ($matches->count() !== 1) {
            throw new InvalidArgumentException("{$matches->count()} groups are named \"{$name}\"; there must be exactly one.");
        }

        return $matches->first();
    }

    /**
     * @param  array<int, array{ascents: int, hills: int, walks: int}>  $totals
     */
    private function names(array $totals): void
    {
        foreach ($totals as $userId => $total) {
            $name = User::query()->whereKey($userId)->value('name') ?? "user {$userId}";
            $this->line(sprintf('  %-24s %4d ascents  %4d hills  %4d walks', $name, $total['ascents'], $total['hills'], $total['walks']));
        }
    }

    /**
     * @param  list<PlannedWalk>  $walks
     */
    private function report(AscentImporter $importer, array $walks): void
    {
        $days = array_sum(array_map(fn (array $walk) => count($walk['days']), $walks));
        $group = count(array_filter($walks, fn (array $walk) => $walk['group'] !== null));

        $this->info(count($walks)." walks ({$group} group, ".(count($walks) - $group)." solo) over {$days} days.");
        $this->names($importer->totals($walks));
    }
}
