<?php
/**
 * Role-Based Access Control Service
 *
 * This file provides comprehensive role-based access control functionality for the system. It handles user permissions, role management, and access validation. The module ensures proper authorization checks, maintains security policies, and supports integration with other system components.
 *
 * @package    InventoryManagementSystem
 * @subpackage Control
 * @author     TMDD Interns 25'
 */
declare(strict_types=1);

final class RBACService
{
    /**
     * @var array $privileges
     * @brief Stores the privileges associated with modules for a user.
     *
     * This array holds the mapping of module names to their respective privileges
     * loaded from the database for the current user.
     */
    private array $privileges = [];

    /**
     * @brief Constructor for RBACService.
     * @param \PDO $pdo Database connection object.
     * @param int $userId The ID of the user whose privileges are to be loaded.
     */
    public function __construct(
        private \PDO $pdo,
        int $userId
    ) {
        $this->loadUserPrivileges($userId);
    }

    /**
     * @brief Loads user privileges from the database.
     * @param int $userId The ID of the user whose privileges are to be loaded.
     * @return void
     */
    private function loadUserPrivileges(int $userId): void
    {
        $sql = <<<'SQL'
        SELECT
          m.module_name,
          p.priv_name
        FROM user_department_roles ur
        JOIN roles r
          ON ur.role_id = r.id
         AND r.is_disabled = 0
        JOIN role_module_privileges rmp
          ON rmp.role_id = r.id
        JOIN modules m 
          ON rmp.module_id = m.id
        JOIN privileges p
          ON rmp.privilege_id = p.id
         AND p.is_disabled = 0
       WHERE ur.user_id = ?
    SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->privileges[$row['module_name']][] = $row['priv_name'];
        }

    }

    /**
     * @brief Checks if the user has a specific privilege for a module.
     * @param string $module The name of the module to check.
     * @param string $priv The name of the privilege to check for.
     * @return bool Returns true if the user has the privilege, false otherwise.
     */
    public function hasPrivilege(string $module, string $priv): bool
    {
        return isset($this->privileges[$module])
            && in_array($priv, $this->privileges[$module], true);
    }

    /**
     * @brief Enforces a privilege check, terminating the request if the user lacks the privilege.
     * @param string $module The name of the module to check.
     * @param string $priv The name of the privilege required.
     * @return void
     */
    public function requirePrivilege(string $module, string $priv): void
    {
        if (! $this->hasPrivilege($module, $priv)) {
            header('HTTP/1.1 403 Forbidden');
            exit('Access denied');
        }
    }

}

