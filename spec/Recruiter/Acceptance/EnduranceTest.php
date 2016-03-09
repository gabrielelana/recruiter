<?php
namespace Recruiter\Acceptance;

use Recruiter\Job\Repository;
use Onebip\Concurrency\Timeout;
use Eris;
use Eris\Generator;
use Eris\Generator\ConstantGenerator;
use Eris\Listener;

class EnduranceTest extends BaseAcceptanceTest
{
    use Eris\TestTrait; 

    public function setUp()
    {
        parent::setUp();
        $this->jobRepository = new Repository($this->recruiterDb);
        $this->actionLog = '/tmp/actions.log';
        $this->files[] = $this->actionLog;
    }

    public function tearDown()
    {
        $this->terminateProcesses(SIGKILL);
    }
    
    public function testNotWithstandingCrashesJobsAreEventuallyPerformed()
    {
        $this
            ->limitTo(100)
            ->forAll(Generator\seq(Generator\oneOf(
                ConstantGenerator::box('enqueueJob'),
                ConstantGenerator::box('restartWorker'),
                ConstantGenerator::box('restartRecruiter'),
                Generator\map(
                    function($milliseconds) {
                        return ['sleep', $milliseconds];
                    },
                    Generator\choose(1, 1000)
                )
            )))
            ->hook(Listener\collectFrequencies(function($actions) {
                return '[' . implode(',', $actions) . ']';
            }))
            ->then(function($actions) {
                $this->clean();
                $this->start();
                foreach ($actions as $action) {
                    $this->logAction($action);
                    if (is_array($action)) {
                        $arguments = $action;
                        $method = array_shift($arguments);
                        call_user_func_array(
                            [$this, $method],
                            $arguments
                        );
                    } else {
                        $this->$action();
                    }
                }

                $estimatedTime = count($actions) * 3;
                Timeout::inSeconds(
                    $estimatedTime,
                    function() {
                        return "all $this->jobs jobs to be performed. Now is " . date('c') . " See: " . var_export($this->files, true);
                    }
                )
                    ->until(function() {
                        return $this->jobRepository->countArchived() === $this->jobs;
                    });
            });
    }

    private function clean()
    {
        $this->terminateProcesses(SIGKILL);
        $this->cleanLogs();
        $this->cleanDb();
        $this->jobs = 0;
    }

    private function logAction($text)
    {
        file_put_contents(
            $this->actionLog,
            sprintf(
                "[ACTIONS][PHPUNIT][%s] %s" . PHP_EOL,
                date('c'), $text
            ),
            FILE_APPEND
        );
    }

    private function terminateProcesses($signal)
    {
        if ($this->processRecruiter) {
            $this->stopProcessWithSignal($this->processRecruiter, $signal);
            $this->processRecruiter = null;
        }
        if ($this->processWorker) {
            $this->stopProcessWithSignal($this->processWorker, $signal);
            $this->processWorker = null;
        }
    }

    private function start()
    {
        $this->processRecruiter = $this->startRecruiter();
        $this->processWorker = $this->startWorker();
    }

    protected function restartWorker()
    {
        $this->stopProcessWithSignal($this->processWorker, SIGTERM);
        $this->processWorker = $this->startWorker();
    }

    protected function restartRecruiter()
    {
        $this->stopProcessWithSignal($this->processRecruiter, SIGTERM);
        $this->processRecruiter = $this->startRecruiter();
    }

    protected function sleep($milliseconds)
    {
        usleep($milliseconds * 1000);
    }
}
