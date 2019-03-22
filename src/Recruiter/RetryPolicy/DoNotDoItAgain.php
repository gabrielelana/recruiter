<?php

namespace Recruiter\RetryPolicy;

use Recruiter\Job;
use Recruiter\RetryPolicy;
use Recruiter\RetryPolicyBehaviour;
use Recruiter\JobAfterFailure;

class DoNotDoItAgain implements RetryPolicy
{
    use RetryPolicyBehaviour;

    public function schedule(JobAfterFailure $job)
    {
        // doing nothing means to avoid to reschedule the job
    }

    public function isLastRetry(Job $job): bool
    {
        return true;
    }
}
