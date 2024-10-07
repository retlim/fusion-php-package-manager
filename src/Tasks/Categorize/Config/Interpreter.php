<?php
/**
 * Fusion. A package manager for PHP-based projects.
 * Copyright Valvoid
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Valvoid\Fusion\Tasks\Categorize\Config;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Config as ConfigEvent;
use Valvoid\Fusion\Config\Interpreter as ConfigInterpreter;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Tasks\Categorize\Categorize;

/**
 * Categorize task config interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Interpreter extends ConfigInterpreter
{
    /**
     * Interprets the categorize task config.
     *
     * @param array $breadcrumb Index path inside the config to the passed sub config.
     * @param mixed $entry Sub config to interpret.
     */
    public static function interpret(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (is_string($entry))
            self::interpretDefaultTask($breadcrumb, $entry);

        elseif (is_array($entry))
            foreach ($entry as $key => $value)
                match ($key) {
                    "task" => self::interpretTask($breadcrumb, $value),
                    "efficiently" => self::interpretEfficiently($breadcrumb, $value),
                    default => Bus::broadcast(new ConfigEvent(
                        "The unknown \"$key\" index must be \"task\", " .
                        "or \"efficiently\" string.",
                        Level::ERROR,
                        [...$breadcrumb, $key]
                    ))
                };

        else Bus::broadcast(new ConfigEvent(
            "The value must be the default \"" . Categorize::class .
            "\" class name string or a configured array task.",
            Level::ERROR,
            $breadcrumb
        ));
    }

    /**
     * Interprets the default task.
     *
     * @param mixed $entry Task entry.
     */
    private static function interpretDefaultTask(array $breadcrumb, mixed $entry): void
    {
        if ($entry !== Categorize::class)
            Bus::broadcast(new ConfigEvent(
                "The value must be the \"" . Categorize::class .
                "\" class name string.",
                Level::ERROR,
                $breadcrumb
            ));
    }

    /**
     * Interprets the task.
     *
     * @param mixed $entry Task entry.
     */
    private static function interpretTask(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if ($entry !== Categorize::class)
            Bus::broadcast(new ConfigEvent(
                "The value, task class name, of the \"task\" " .
                "index must be the \"" . Categorize::class . "\" string.",
                Level::ERROR,
                [...$breadcrumb, "task"]
            ));
    }

    /**
     * Interprets the efficiently entry.
     *
     * @param mixed $entry Source entry.
     */
    private static function interpretEfficiently(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_bool($entry))
            Bus::broadcast(new ConfigEvent(
                "The value of the \"efficiently\" " .
                "index must be a boolean.",
                Level::ERROR,
                [...$breadcrumb, "efficiently"]
            ));
    }
}