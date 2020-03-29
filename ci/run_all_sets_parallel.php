<?php

declare(strict_types=1);

use Rector\Core\Set\SetProvider;
use Symfony\Component\Process\Process;
use Symplify\PackageBuilder\Console\ShellCode;

require __DIR__.'/../vendor/autoload.php';

$setProvider = new SetProvider();

// any file to be tested
$file         = 'src/Rector/AbstractRector.php';
$excludedSets = [
    // required Kernel class to be set in parameters
    'symfony-code-quality',
];

$taskRunner   = new TaskRunner();
$tasks        = [];
$maxProcesses = 1;
$maxProcesses = num_cpu();
$sleepSeconds = 1;
foreach ($setProvider->provide() as $setName) {
    if (in_array($setName, $excludedSets, true)) {
        continue;
    }
    $tasks[$setName] = new Task($file, $setName);
}

if ($taskRunner->run($tasks, $maxProcesses, $sleepSeconds)) {
    exit(ShellCode::SUCCESS);
}
exit(ShellCode::ERROR);

class TaskRunner
{
    /**
     * @param Task[] $tasks
     */
    public function run(array $tasks, int $maxProcesses = 1, int $sleepSeconds = 1): bool
    {
        $this->printInfo($tasks, $maxProcesses);

        $success = true;

        /**
         * @var Process[] $runningProcesses
         */
        $runningProcesses = [];
        $remainingTasks   = $tasks;
        $finished         = 0;
        $total            = count($tasks);
        do {
            $this->sleepIfNecessary($remainingTasks, $runningProcesses, $maxProcesses, $sleepSeconds);

            // start a new process for the given task
            if ($this->canStartAnotherProcess($remainingTasks, $runningProcesses, $maxProcesses)) {
                $setName = array_key_first($remainingTasks);
                $task    = array_shift($remainingTasks);

                try {
                    $process = $this->createProcess($task);
                    $process->start();
                    $runningProcesses[$setName] = $process;
                } catch (Throwable $t) {
                    $success = false;
                    $this->printError($setName, $t, $total, $finished);
                }
            }

            // regularly check  if they a running process is finished
            foreach ($runningProcesses as $setName => $process) {
                if (!$process->isRunning()) {
                    $finished++;
                    unset($runningProcesses[$setName]);
                    try {
                        $this->printSuccess($setName, $total, $finished);
                    } catch (Throwable $t) {
                        $success = false;
                        $this->printError($setName, $t, $total, $finished);
                    }
                }
            }

            $someProcessesAreStillRunning = count($runningProcesses) > 0;
            $notAllProcessesAreStartedYet = count($remainingTasks) > 0;
        } while ($someProcessesAreStillRunning || $notAllProcessesAreStartedYet);

        return $success;
    }

    /**
     * We should sleep when the processes are running in order to not
     * exhaust system resources. But we only wanna do this when
     * we can't start another processes:
     * either because none are left or
     * because we reached the threshold of allowed processes
     *
     * @param string[] $taskIdsToRuns
     * @param Process[] $runningProcesses
     */
    private function sleepIfNecessary(
        array $taskIdsToRuns,
        array $runningProcesses,
        int $maxProcesses,
        int $secondsToSleep
    ): void {
        $noMoreProcessesAreLeft         = count($taskIdsToRuns) === 0;
        $maxNumberOfProcessesAreRunning = count($runningProcesses) >= $maxProcesses;
        if ($noMoreProcessesAreLeft || $maxNumberOfProcessesAreRunning) {
            sleep($secondsToSleep);
        }
    }

    private function canStartAnotherProcess(array $remainingTasks, array $runningProcesses, int $max): bool
    {
        $hasOpenTasks              = count($remainingTasks) > 0;
        $moreProcessesCanBeStarted = count($runningProcesses) < $max;
        return $hasOpenTasks && $moreProcessesCanBeStarted;
    }

    private function createProcess(Task $task): Process
    {
        $command = ['php', 'bin/rector', 'process', $task->getPathToFile(), '--set', $task->getSetName(), '--dry-run'];

        return new Process($command, __DIR__.'/..');
    }

    private function printSuccess(string $set, int $totalTasks, int $finishedTasks): void
    {
        echo sprintf('✔ Set "%s" is OK (%2d / %2d)'.PHP_EOL, $set, $finishedTasks, $totalTasks);
    }

    private function printError(string $set, Throwable $t, int $totalTasks, int $finishedTasks): void
    {
        echo sprintf('❌ Set "%s" failed: %s (%2d / %2d)'.PHP_EOL, $set, $t->getMessage(), $finishedTasks, $totalTasks);
    }

    private function printInfo(array $tasks, int $maxProcesses): void
    {
        echo sprintf('Running %d sets with %d parallel processes'.PHP_EOL.PHP_EOL, count($tasks), $maxProcesses);
    }
}

class Task
{
    /**
     * @var string
     */
    private $pathToFile;

    /**
     * @var string
     */
    private $setName;

    public function __construct(string $pathToFile, string $setName)
    {
        $this->pathToFile = $pathToFile;
        $this->setName    = $setName;
    }

    /**
     * @return string
     */
    public function getPathToFile(): string
    {
        return $this->pathToFile;
    }

    /**
     * @return string
     */
    public function getSetName(): string
    {
        return $this->setName;
    }
}

/**
 * @see https://gist.github.com/divinity76/01ef9ca99c111565a72d3a8a6e42f7fb
 *
 * returns number of cpu cores
 * Copyleft 2018, license: WTFPL
 * @return int
 */
function num_cpu(): int
{
    if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
        $str = trim(shell_exec('wmic cpu get NumberOfCores 2>&1'));
        if (!preg_match('/(\d+)/', $str, $matches)) {
            throw new \RuntimeException ('wmic failed to get number of cpu cores on windows!');
        }
        return (( int ) $matches [1]);
    }
    $ret = @shell_exec('nproc');
    if (is_string($ret)) {
        $ret = trim($ret);
        if (false !== ($tmp = filter_var($ret, FILTER_VALIDATE_INT))) {
            return $tmp;
        }
    }
    if (is_readable('/proc/cpuinfo')) {
        $cpuinfo = file_get_contents('/proc/cpuinfo');
        $count   = substr_count($cpuinfo, 'processor');
        if ($count > 0) {
            return $count;
        }
    }
    throw new \LogicException('failed to detect number of CPUs!');
}
