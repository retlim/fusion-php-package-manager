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

namespace Valvoid\Fusion;

use Exception;
use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Root;
use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Config\Config;
use Valvoid\Fusion\Hub\Hub;
use Valvoid\Fusion\Log\Events\Errors\Config as ConfigError;
use Valvoid\Fusion\Log\Events\Errors\Error as InternalError;
use Valvoid\Fusion\Log\Events\Errors\Metadata as MetadataError;
use Valvoid\Fusion\Log\Events\Event as LogEvent;
use Valvoid\Fusion\Log\Events\Infos\Id;
use Valvoid\Fusion\Log\Events\Infos\Name;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;

/**
 * Package manager for PHP-based projects.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Fusion
{
    /** @var ?Fusion Runtime instance. */
    private static ?Fusion $instance = null;

    /** @var string Source root directory. */
    private string $root;

    /** @var Config Composite settings. */
    private Config $config;

    /** @var Dir Current package directory. */
    private Dir $dir;

    /** @var Log Log. */
    private Log $log;

    /** @var Bus Event bus. */
    private Bus $bus;

    /** @var Hub Hub. */
    private Hub $hub;

    /** @var bool Lock indicator. */
    private bool $busy = false;

    /** @var array Lazy code registry. */
    private array $lazy;

    /**
     * Constructs the package manager.
     *
     * @param array $config Runtime config layer.
     * @throws ConfigError Invalid config exception.
     * @throws MetadataError Invalid metadata exception.
     */
    private function __construct(array $config)
    {
        $this->root = dirname(__DIR__);
        $this->lazy = require $this->root . "/cache/loadable/lazy.php";

        spl_autoload_register($this->loadLazyLoadable(...));

        $this->bus = Bus::___init();
        $this->config = Config::___init($this->root, $this->lazy, $config);
        $this->dir = Dir::___init();
        $this->log = Log::___init();
        $this->hub = Hub::___init();

        Bus::addReceiver(self::class, $this->handleBusEvent(...),
            Root::class);
    }

    /**
     * Initializes the package manager instance.
     *
     * @param array $config Runtime config layer.
     * @return bool True for success. False for has destroyable instance.
     * @throws ConfigError Invalid config exception.
     * @throws MetadataError Invalid metadata exception.
     */
    public static function init(array $config = []): bool
    {
        if (self::$instance !== null)
            return false;

        self::$instance = new self($config);

        return true;
    }

    /**
     * Loads lazy loadable.
     *
     * @param string $loadable Loadable.
     */
    private function loadLazyLoadable(string $loadable): void
    {
        // registered
        // hide unregistered warning
        // show custom error
        if (@$file = $this->lazy[$loadable])
            require $this->root . $file;
    }

    /**
     * Destroys the package manager instance.
     *
     * @return bool True for success.
     */
    public static function destroy(): bool
    {
        $fusion = &self::$instance;

        if ($fusion === null)
            return true;

        if ($fusion->busy)
            return false;

        $fusion->bus::removeReceiver(self::class);

        $fusion->dir->destroy();
        $fusion->log->destroy();
        $fusion->config->destroy();
        $fusion->hub->destroy();
        $fusion->bus->destroy();

        $fusion = null;

        return true;
    }

    /**
     * Manages project changes generated by task or task
     * group execution.
     *
     * @param string $id Callable task or group ID.
     * @throws Exception Destroyed object exception.
     * @return bool True for success.
     */
    public static function manage(string $id): bool
    {
        $fusion = self::$instance;

        if ($fusion === null || $fusion->busy)
            return false;

        $fusion->busy = true;

        try {
            $entry = $fusion->config::get("tasks", $id) ??
                throw new InternalError(
                    "Task id \"$id\" does not exist."
                );

            Log::info(new Id($id));
            $fusion->dir->normalize();

            /** @var Task $task */
            if (isset($entry["task"])) {
                $task = new $entry["task"]($entry);

                $fusion->log->addInterceptor($task);
                $task->execute();
                $fusion->log->removeInterceptor();

            } else {
                $group = Group::___init();

                foreach ($entry as $taskId => $task) {
                    Log::info(new Name($taskId));

                    $task = new $task["task"]($task);

                    $fusion->log->addInterceptor($task);
                    $task->execute();
                    $fusion->log->removeInterceptor();
                }

                $group->destroy();
            }

        } catch (LogEvent $error) {
            Log::error($error);
        }

        $fusion->dir->normalize();
        $fusion->busy = false;

        return !isset($error);
    }

    /**
     * Handles bus event.
     *
     * @param Root $event Root event.
     */
    private function handleBusEvent(Root $event): void
    {
        $this->root = $event->getDir();
    }
}