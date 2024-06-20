<?php declare(strict_types=1);
/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

// File under test
require_once HV_ROOT_DIR.'/../src/Database/ClientState.php';

final class ClientStateTest extends TestCase
{

    public $createdIds = [];


    public function testItShouldCreateClientState(): void 
    {
        $state = [
            'events' => [
                'type'=>'HEK+YEAH'
            ]
        ];

        $client_state= new ClientState();

        // just a precaution to clean database after we are done
        $this->createdIds[] = $client_state->upsert($state);

        $count_sql = sprintf("SELECT COUNT(*) FROM client_states WHERE id = '%s'", hash('sha256', json_encode($state)));

        $result = $client_state->query($count_sql);

        $this->assertEquals($result->num_rows, 1);
    }


    public function testItShouldOnlyCreateDBInstancePerClientState(): void
    {
        $state = [
            'events' => [
                'type'=>'RHESSI+YEAH'
            ]
        ];

        $client_state= new ClientState();

        $state_key = $client_state->upsert($state);
        $client_state->upsert($state);
        $client_state->upsert($state);

        $count_sql = sprintf("SELECT COUNT(*) FROM client_states WHERE id = '%s'", hash('sha256', json_encode($state)));

        $result = $client_state->query($count_sql);

        $this->createdIds[] = $state_key;
        $this->assertEquals($result->num_rows, 1);
    }

    public function testItShouldCreateIdOfStateShouldBeHashOfJson(): void 
    {
        $state = [
            'events' => [
                'type'=>'CCMC+YEAH'
            ]
        ];

        $client_state= new ClientState();

        $state_key = $client_state->upsert($state);
        $this->createdIds[] = $state_key;

        $this->assertEquals($state_key, hash('sha256', json_encode($state)));
    }

    public function testItShouldFindPreviouslyCreatedStates(): void 
    {
        $state = [
            'events' => [
                'type'=>'FOO+YEAH'
            ]
        ];

        $client_state= new ClientState();

        $state_key = $client_state->upsert($state);
        $this->createdIds[] = $state_key;

        $db_found_state = $client_state->find($state_key);

        $this->assertEquals($state, $db_found_state);
    }

    public function testItShouldNotFindNonExistingStates(): void 
    {
        $client_state= new ClientState();

        $state = $client_state->find("foo");

        $this->assertNull($state);
    }

    public function tearDown(): void
    {
        $client_state = new ClientState();
        $client_state->query(sprintf("DELETE FROM client_states WHERE id in ('%s') LIMIT %d", join("','", $this->createdIds), count($this->createdIds)));
    }



}

