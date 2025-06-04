<?php
/**
 * @file websocket_server.php
 * @brief WebSocket server for real-time communication.
 *
 * This script sets up a WebSocket server to handle real-time communication
 * between clients and the server, facilitating instant updates and notifications.
 */
require '../../../../config/ims-tmdd.php';
require '../../../../config/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;

/**
 * @class ActivityStream
 * @brief Manages WebSocket connections and activity broadcasting.
 *
 * This class implements the MessageComponentInterface to handle WebSocket connections,
 * manage client connections, and broadcast activities to connected clients.
 */
class ActivityStream implements \Ratchet\MessageComponentInterface {
    /**
     * @var \SplObjectStorage $clients
     * @brief Stores connected WebSocket clients.
     *
     * This object storage holds all active client connections for broadcasting messages.
     */
    protected $clients;

    /**
     * @var \PDO $pdo
     * @brief Database connection object.
     *
     * This variable holds the PDO instance for database operations.
     */
    protected $pdo;

    /**
     * @brief Constructor for ActivityStream.
     * @param \PDO $pdo Database connection object.
     */
    public function __construct($pdo) {
        $this->clients = new \SplObjectStorage;
        $this->pdo = $pdo;
    }

    /**
     * @brief Retrieves the list of connected clients.
     * @return \SplObjectStorage Returns the storage object containing all connected clients.
     */
    public function getClients() {
        return $this->clients;
    }

    /**
     * @brief Handles new WebSocket connections.
     * @param \Ratchet\ConnectionInterface $conn The new connection object.
     * @return void
     */
    public function onOpen(\Ratchet\ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    /**
     * @brief Handles incoming messages from clients.
     * @param \Ratchet\ConnectionInterface $from The client sending the message.
     * @param string $msg The received message.
     * @return void
     */
    public function onMessage(\Ratchet\ConnectionInterface $from, $msg) {
        // Handle any incoming messages if needed
    }

    /**
     * @brief Handles client disconnection.
     * @param \Ratchet\ConnectionInterface $conn The disconnecting client connection.
     * @return void
     */
    public function onClose(\Ratchet\ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    /**
     * @brief Handles errors during WebSocket communication.
     * @param \Ratchet\ConnectionInterface $conn The connection where the error occurred.
     * @param \Exception $e The exception object containing error details.
     * @return void
     */
    public function onError(\Ratchet\ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * @brief Broadcasts activity updates to all connected clients.
     * @param mixed $activity The activity data to broadcast.
     * @return void
     */
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