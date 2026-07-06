<?php
if (!defined('ABSPATH')) exit;

final class YooY_Mock_Job_Engine {

    private const OPTION_KEY = 'yoy_mock_jobs';

    public function create(string $job_id, array $payload, callable $build_completed): array {
        $job = [
            'job_id'     => $job_id,
            'status'     => YooY_Job_Status::QUEUED,
            'progress'   => 0,
            'polls'      => 0,
            'payload'    => $payload,
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'result'     => null,
            'builder'    => null,
        ];

        $jobs = $this->all();
        $jobs[$job_id] = $job;
        $this->save($jobs);

        return $this->format_status($job);
    }

    public function register_result(string $job_id, array $completed_result): void {
        $jobs = $this->all();
        if (!isset($jobs[$job_id])) return;

        $jobs[$job_id]['result'] = $completed_result;
        $this->save($jobs);
    }

    public function poll(string $job_id): array {
        $jobs = $this->all();
        if (!isset($jobs[$job_id])) {
            return [
                'job_id'   => $job_id,
                'status'   => YooY_Job_Status::FAILED,
                'progress' => 0,
                'error'    => 'Job not found.',
            ];
        }

        $job = $jobs[$job_id];
        $job['polls'] = (int) ($job['polls'] ?? 0) + 1;

        if ($job['status'] === YooY_Job_Status::QUEUED) {
            $job['status']   = YooY_Job_Status::RUNNING;
            $job['progress'] = 25;
        } elseif ($job['status'] === YooY_Job_Status::RUNNING) {
            if ($job['polls'] >= 2 && !empty($job['result'])) {
                $job['status']   = YooY_Job_Status::COMPLETED;
                $job['progress'] = 100;
            } else {
                $job['progress'] = min(90, 25 + ($job['polls'] * 30));
            }
        }

        $job['updated_at'] = gmdate('c');
        $jobs[$job_id] = $job;
        $this->save($jobs);

        if ($job['status'] === YooY_Job_Status::COMPLETED && !empty($job['result'])) {
            return array_merge($job['result'], [
                'status'   => YooY_Job_Status::COMPLETED,
                'progress' => 100,
            ]);
        }

        return $this->format_status($job);
    }

    public function complete_immediately(string $job_id, array $result): array {
        $jobs = $this->all();
        $jobs[$job_id] = [
            'job_id'     => $job_id,
            'status'     => YooY_Job_Status::COMPLETED,
            'progress'   => 100,
            'polls'      => 1,
            'payload'    => [],
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'result'     => $result,
        ];
        $this->save($jobs);
        return array_merge($result, ['status' => YooY_Job_Status::COMPLETED, 'progress' => 100]);
    }

    private function format_status(array $job): array {
        return [
            'job_id'   => $job['job_id'],
            'status'   => $job['status'],
            'progress' => (int) ($job['progress'] ?? 0),
            'provider' => $job['result']['provider'] ?? ($job['payload']['provider'] ?? 'mock'),
            'model'    => $job['result']['model'] ?? ($job['payload']['model'] ?? ''),
        ];
    }

    private function all(): array {
        $stored = get_option(self::OPTION_KEY, []);
        return is_array($stored) ? $stored : [];
    }

    private function save(array $jobs): void {
        update_option(self::OPTION_KEY, array_slice($jobs, -500, null, true), false);
    }
}
