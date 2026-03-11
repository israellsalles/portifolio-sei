<?php

// Roles de usuario
define('ROLE_ADMIN',   'admin');
define('ROLE_EDICAO',  'edicao');
define('ROLE_LEITURA', 'leitura');

// Status de sistemas
define('STATUS_ATIVO',       'Ativo');
define('STATUS_MANUTENCAO',  'Manutenção');
define('STATUS_IMPLANTACAO', 'Implantação');
define('STATUS_DEPRECIADO',  'Depreciado');
define('STATUS_SUSPENSO',    'Suspenso');

// Criticidade de sistemas
define('CRIT_ALTA',  'Alta');
define('CRIT_MEDIA', 'Media');
define('CRIT_BAIXA', 'Baixa');

// Categorias de VM
define('VM_CAT_PRODUCAO',       'Producao');
define('VM_CAT_HOMOLOGACAO',    'Homologacao');
define('VM_CAT_DESENVOLVIMENTO','Desenvolvimento');

// Tipos de VM
define('VM_TYPE_SISTEMAS', 'Sistemas');
define('VM_TYPE_SGBD',     'SGBD');

// Acesso de VM
define('VM_ACCESS_INTERNO', 'Interno');
define('VM_ACCESS_EXTERNO', 'Externo');

// Administracao de VM
define('VM_ADMIN_SEI',    'SEI');
define('VM_ADMIN_PRODEB', 'PRODEB');

// Rate limiting de login
define('LOGIN_MAX_ATTEMPTS', 10);
define('LOGIN_WINDOW_SECS',  300); // 5 minutos
