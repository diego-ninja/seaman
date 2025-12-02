<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class DuetimeManipulator implements TableManipulator
{
    final public const TYPE = 'duetime';

    public function manipulate(mixed $value): ?string
    {
        if (!$value) {
            return '';
        }

        $isPast = false;
        if ($value > time()) {
            $seconds = $value - time();
        } else {
            $isPast  = true;
            $seconds = time() - $value;
        }

        $timeUnits = $this->secondsToTimeUnits($seconds);
        $text      = $this->formatTimeUnits($timeUnits);

        return $text . ($isPast ? ' ago' : '');
    }

    /**
     * @param int $seconds
     * @return array<string, float|int<min, 59>>
     */
    protected function secondsToTimeUnits(int $seconds): array
    {
        $timeUnits = [
            'years'   => 0,
            'days'    => 0,
            'hours'   => 0,
            'minutes' => 0,
            'seconds' => 0,
        ];

        if ($seconds >= 60) {
            $timeUnits['minutes'] = floor($seconds / 60);
            $seconds -= $timeUnits['minutes'] * 60;
        }

        if ($timeUnits['minutes'] >= 60) {
            $timeUnits['hours'] = floor($timeUnits['minutes'] / 60);
            $timeUnits['minutes'] -= $timeUnits['hours'] * 60;
        }

        if ($timeUnits['hours'] >= 24) {
            $timeUnits['days'] = floor($timeUnits['hours'] / 24);
            $timeUnits['hours'] -= $timeUnits['days'] * 24;
        }

        if ($timeUnits['days'] >= 365) {
            $timeUnits['years'] = floor($timeUnits['days'] / 365);
            $timeUnits['days'] -= $timeUnits['years'] * 365;
        }

        $timeUnits['seconds'] = $seconds;

        return $timeUnits;
    }

    /**
     * @param array<string, float|int<min, 59>> $timeUnits
     */
    protected function formatTimeUnits(array $timeUnits): string
    {
        $text = '';

        foreach ($timeUnits as $unit => $value) {
            if ($value > 0) {
                $text .= $value . ' ' . $unit . ($value === 1 ? '' : 's') . ', ';
            }
        }

        return rtrim($text, ', ');
    }
}
