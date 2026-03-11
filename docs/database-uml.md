# Diagrama UML do Banco de Dados

Fonte do schema: `app/bootstrap.php`.

Observacao: os relacionamentos abaixo representam o modelo fisico no SQLite com `FOREIGN KEY` explicita (integridade referencial no banco).

```mermaid
classDiagram
direction LR

class systems {
  +INTEGER id <<PK>>
  +TEXT name
  +TEXT system_name
  +TEXT system_group
  +INTEGER vm_id <<FK>>
  +INTEGER vm_homolog_id <<FK>>
  +INTEGER vm_dev_id <<FK>>
  +TEXT category
  +TEXT status
  +TEXT tech
  +INTEGER archived
  +TEXT archived_at
  +TEXT created_at
  +TEXT updated_at
}

class virtual_machines {
  +INTEGER id <<PK>>
  +TEXT name
  +TEXT ip
  +TEXT vm_category
  +TEXT vm_type
  +TEXT vm_access
  +TEXT vm_administration
  +TEXT vm_language
  +TEXT vm_tech
  +TEXT diagnostic_json_ref
  +TEXT diagnostic_json_ref_r
  +INTEGER archived
  +TEXT archived_at
  +TEXT created_at
  +TEXT updated_at
}

class system_databases {
  +INTEGER id <<PK>>
  +INTEGER system_id <<FK>>
  +INTEGER vm_id <<FK>>
  +INTEGER vm_homolog_id <<FK>>
  +TEXT db_name
  +TEXT db_user
  +TEXT db_engine
  +TEXT db_engine_version
  +INTEGER archived
  +TEXT archived_at
  +TEXT created_at
  +TEXT updated_at
}

class users {
  +INTEGER id <<PK>>
  +TEXT username
  +TEXT password_hash
  +TEXT full_name
  +TEXT role
  +INTEGER active
  +TEXT created_at
  +TEXT updated_at
}

class login_attempts {
  +INTEGER id <<PK>>
  +TEXT ip
  +TEXT attempted_at
}

virtual_machines "1" <-- "0..*" systems : vm_id (producao) [DEL SET NULL | UPD CASCADE]
virtual_machines "1" <-- "0..*" systems : vm_homolog_id (homologacao) [DEL SET NULL | UPD CASCADE]
virtual_machines "1" <-- "0..*" systems : vm_dev_id (desenvolvimento) [DEL SET NULL | UPD CASCADE]

systems "1" <-- "0..*" system_databases : system_id [DEL CASCADE | UPD CASCADE]
virtual_machines "1" <-- "0..*" system_databases : vm_id [DEL SET NULL | UPD CASCADE]
virtual_machines "1" <-- "0..*" system_databases : vm_homolog_id [DEL SET NULL | UPD CASCADE]
```

## Relacoes FK (fisicas)

| Tabela filha | Coluna FK | Referencia | ON DELETE | ON UPDATE |
|---|---|---|---|---|
| `systems` | `vm_id` | `virtual_machines(id)` | `SET NULL` | `CASCADE` |
| `systems` | `vm_homolog_id` | `virtual_machines(id)` | `SET NULL` | `CASCADE` |
| `systems` | `vm_dev_id` | `virtual_machines(id)` | `SET NULL` | `CASCADE` |
| `system_databases` | `system_id` | `systems(id)` | `CASCADE` | `CASCADE` |
| `system_databases` | `vm_id` | `virtual_machines(id)` | `SET NULL` | `CASCADE` |
| `system_databases` | `vm_homolog_id` | `virtual_machines(id)` | `SET NULL` | `CASCADE` |
