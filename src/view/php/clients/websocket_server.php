<?php
require '../../../../config/ims-tmdd.php';
require '../../../../config/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;

class ActivityStream implements \Ratchet\MessageComponentInterface {
    protected $clients;
    protected $pdo;

    public function __construct($pdo) {
        $this->clients = new \SplObjectStorage;
        $this->pdo = $pdo;
    }

    public function getClients() {
        return $this->clients;
    }

    public function onOpen(\Ratchet\ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(\Ratchet\ConnectionInterface $from, $msg) {
        // Handle any incoming messages if needed
    }

    public function onClose(\Ratchet\ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(\Ratchet\ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    public function broadcastActivity($activity) {
        foreach ($this->clients as $client) {
            $client->send(json_encode($activity));
        }
    }
}

// Create event loop
$loop = Factory::create();

// Create WebSocket server
$activityStream = new ActivityStream($pdo);
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            $activityStream
        )
    ),
    8080
);

// Start checking for new activities
$server->loop->addPeriodicTimer(5, function() use ($activityStream, $pdo) {
    try {
        // Get all connected clients and their user IDs
        $userModules = [];
        foreach ($activityStream->getClients() as $client) {
            if (isset($client->userId)) {
                // Get modules with track permissions for this user
                $query = $pdo->prepare("
                    SELECT DISTINCT m.module_name
                    FROM modules m
                    JOIN role_module_privileges rmp ON m.id = rmp.module_id
                    JOIN privileges p ON rmp.privilege_id = p.id
                    JOIN user_department_roles udr ON rmp.role_id = udr.role_id
                    WHERE udr.user_id = ?
                    AND p.priv_name = 'track'
                ");
                $query->execute([$client->userId]);
                $userModules[$client->userId] = $query->fetchAll(PDO::FETCH_COLUMN);
            }
        }

        // Get new activities for each user's modules
        foreach ($userModules as $userId => $modules) {
            if (empty($modules)) continue;

            $placeholders = str_repeat('?,', count($modules) - 1) . '?';
            $query = $pdo->prepare("
                SELECT 
                    al.TrackID as id,
                    al.Action,
                    al.Details as description,
                    al.Date_Time as created_at,
                    u.email as user_email,
                    al.Module as module_name
                FROM audit_log al
                JOIN users u ON al.UserID = u.id
                WHERE al.Module IN ($placeholders)
                AND al.Date_Time >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
                ORDER BY al.Date_Time DESC
            ");
            
            $query->execute($modules);
            $newActivities = $query->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($newActivities)) {
                // Send activities to the specific user
                foreach ($activityStream->getClients() as $client) {
                    if ($client->userId === $userId) {
                        foreach ($newActivities as $activity) {
                            $client->send(json_encode($activity));
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error in activity stream: " . $e->getMessage());
    }
});

echo "WebSocket server started on port 8080\n";
$server->run(); 