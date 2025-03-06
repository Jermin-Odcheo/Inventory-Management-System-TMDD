<?php
class RBACService {
    private $pdo;
    private $userId;
    private $modulePrivileges = [];

    public function __construct(PDO $pdo, int $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->loadUserPrivileges();
    }

    private function loadUserPrivileges() {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT m.module_name, p.priv_name
            FROM role_module_privileges rmp
            JOIN modules m ON m.id = rmp.module_id
            JOIN privileges p ON p.id = rmp.privilege_id
            JOIN user_roles ur ON ur.role_id = rmp.role_id
            WHERE ur.user_id = ?
        ");

        $stmt->execute([$this->userId]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->modulePrivileges[$row['module_name']][] = $row['priv_name'];
        }
    }

    public function hasPrivilege(string $moduleName, string $privilegeName): bool {
        return isset($this->modulePrivileges[$moduleName]) &&
            in_array($privilegeName, $this->modulePrivileges[$moduleName]);
    }

    public function getAllPrivilegesForModule(string $moduleName): array {
        return $this->modulePrivileges[$moduleName] ?? [];
    }
}