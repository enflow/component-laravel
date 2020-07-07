<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

trait CommandHelpers
{
    protected function timeProcess(Process $process)
    {
        $process->start();

        ProgressBar::setPlaceholderFormatterDefinition('elapsed_seconds', function (ProgressBar $bar, OutputInterface $output) {
            return (time() - $bar->getStartTime()) . ' secs';
        });

        $progressBar = new ProgressBar($this->output, $process->getTimeout());
        $progressBar->setFormat(' %elapsed_seconds%/%estimated%');

        while ($process->isRunning()) {
            sleep(1);

            $progressBar->advance();
        }

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $progressBar->finish();

        $this->info('');
    }

    protected function mysqlVersion()
    {
        $output = shell_exec('mysql -V');
        preg_match('@[0-9]+\.[0-9]+\.[0-9]+@', $output, $version);

        return $version[0] ?? null;
    }
}
