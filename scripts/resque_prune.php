<?php
/**
 * Drop the Redis registrations of workers that no longer exist.
 *
 * A worker registers itself (host:pid:queues) when it starts and unregisters
 * when it is shut down cleanly. A container that is stopped or recreated
 * kills its workers before they can, and their names stay in Redis: a worker
 * prunes only the dead names of its own host, and a recreated container has
 * a new host name. The names are harmless to the queue but are counted as
 * workers by movie_queue.tcsh and by any Resque dashboard. This script, run
 * by movie_queue.tcsh before the count, removes the names of every other
 * host and the dead ones of this host. It assumes one movies container per
 * Redis, which is how the movie host is run.
 *
 * Environment: QUEUE and REDIS_BACKEND as for resque.php.
 */
require_once __DIR__ . '/../lib/Resque.php';
require_once __DIR__ . '/../lib/Resque/Worker.php';

$backend = getenv('REDIS_BACKEND');
if (!empty($backend)) {
    Resque::setBackend($backend);
}

$self = new Resque_Worker(explode(',', getenv('QUEUE') ?: '*'));
$host = explode(':', (string) $self, 2)[0];
$pruned = 0;

foreach (Resque_Worker::all() as $worker) {
    if (!is_object($worker)) {
        continue;
    }
    [$workerHost] = explode(':', (string) $worker, 2);
    if ($workerHost === $host) {
        continue;                      // this host: pruneDeadWorkers below
    }
    $worker->unregisterWorker();
    $pruned++;
}

$before = count(Resque_Worker::all());
$self->pruneDeadWorkers();
$pruned += $before - count(Resque_Worker::all());

echo "Pruned $pruned dead worker registration(s); " . count(Resque_Worker::all()) . " remain.\n";
