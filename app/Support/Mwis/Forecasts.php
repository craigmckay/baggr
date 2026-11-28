<?php

namespace App\Support\Mwis;

use App\Models\MwisForecast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The forecasts a page may show. There are none unless forecasts are switched on
 * and someone is signed in (spec §4), so pages need no checks of their own.
 *
 * The target day is the coming Saturday if the forecast covers it, otherwise the
 * furthest-out day it covers. If every day held has passed, because fetches have
 * been failing, the latest one is still shown, and marked as old.
 */
class Forecasts
{
    /** @var Collection<int, MwisForecast>|null */
    private ?Collection $held = null;

    public static function visible(): bool
    {
        return config('mwis.enabled') && auth()->check();
    }

    /** The target-day forecast for an area, or null if there is nothing to show. */
    public function forArea(?string $code): ?MwisForecast
    {
        return $this->target($this->held()->where('area', $code)->values());
    }

    /** The day the forecasts are for, to head a page with. */
    public function day(): ?CarbonImmutable
    {
        $target = $this->target($this->held());

        return $target === null ? null : CarbonImmutable::parse($target->date->toDateString());
    }

    /** A day that has gone by: the forecast couldn't be refreshed. */
    public function isOld(MwisForecast $forecast): bool
    {
        return $forecast->date->toDateString() < $this->today()->toDateString();
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('mwis.timezone'));
    }

    /**
     * @param  Collection<int, MwisForecast>  $forecasts
     */
    private function target(Collection $forecasts): ?MwisForecast
    {
        $today = $this->today();
        $coming = $forecasts->filter(fn (MwisForecast $forecast) => ! $this->isOld($forecast));

        if ($coming->isEmpty()) {
            return $forecasts->last();
        }

        $saturday = ($today->isSaturday() ? $today : $today->next('Saturday'))->toDateString();

        return $coming->first(fn (MwisForecast $forecast) => $forecast->date->toDateString() === $saturday) ?? $coming->last();
    }

    /**
     * Read once: there are only a few days for each of ten areas.
     *
     * @return Collection<int, MwisForecast>
     */
    private function held(): Collection
    {
        return $this->held ??= self::visible()
            ? MwisForecast::query()->orderBy('date')->orderBy('id')->get()
            : new Collection;
    }
}
