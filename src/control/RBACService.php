<?php

declare(strict_types=1);

final class RBACService
{
    private array $privileges = [];

    public function __construct(
        private \PDO $pdo,
        int $userId
    ) {
        $this->loadUserPrivileges($userId);
    }

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

    public function hasPrivilege(string $module, string $priv): bool
    {
        return isset($this->privileges[$module])
            && in_array($priv, $this->privileges[$module], true);
    }

    public function requirePrivilege(string $module, string $priv): void
    {
        if (! $this->hasPrivilege($module, $priv)) {
            header('HTTP/1.1 403 Forbidden');
            exit('Access denied');
        }
    }

}
