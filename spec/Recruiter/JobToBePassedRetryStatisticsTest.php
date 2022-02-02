<?php

namespace Recruiter;

use Timeless as T;
use Recruiter\RetryPolicy\DoNotDoItAgain;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class JobToBePassedRetryStatisticsTest extends TestCase
{
    public function setUp(): void
    {
        $this->repository = $this
            ->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testTakeRetryPolicyFromRetriableInstance()
    {
        $workable = new WorkableThatUsesRetryStatistics();

        $job = Job::around($workable, $this->repository);
        $job->execute($this->createMock(EventDispatcherInterface::class));
        $this->assertTrue($job->done(), "Job requiring retry statistics was not executed correctly: " . var_export($job->export(), true));
    }
}

class WorkableThatUsesRetryStatistics implements Workable, Retriable
{
    use WorkableBehaviour;

    public function retryWithPolicy()
    {
        return new DoNotDoItAgain();
    }

    public function execute(array $retryStatistics)
    {
    }
}
