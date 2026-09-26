<?php
return [
    'app' => [
        'name' => 'TRAXTER RH',
        'version' => '1.0.0',
        'release_date' => '',
        'base_url' => '',
        'public_jobs_url' => '',
        'env' => 'auto'
    ],
    // Identidade da empresa cliente desta instalação
    'cliente' => [
        'nome' => 'Empresa Exemplo',          // aparece no portal de vagas, títulos e exportações
        'logo' => '',                         // ex.: 'uploads/marca/logo.png' (PNG ou WEBP claro, para fundo escuro). Vazio = logo TRAXTER
        'site' => '',                         // ex.: 'https://www.empresa.com.br'
        'cores' => [                          // formato #rrggbb
            'escuro' => '#0d1321',            // cabeçalho, menu lateral, títulos
            'medio'  => '#1d2d44',            // botões e destaques
            'claro'  => '#3e5c76'             // detalhes, bordas, foco
        ]
    ],
    // Arquivos privados (currículos, logs, sessões).
    // Vazio = pasta storage/ do projeto (ok no Laragon).
    // Em produção use uma pasta FORA da pública, ex.: '/home/USUARIO/traxter-rh-storage'
    'storage' => [
        'path' => ''
    ],
    // Suporte exibido no manual do sistema
    'suporte' => [
        'nome' => 'TRAXTER Sistemas e Automações',
        'site' => 'https://traxter.com.br/',
        'whatsapp' => '5567998723814'
    ],
    'database' => [
        'dsn' => '',
        'user' => '',
        'pass' => ''
    ],
    'security' => [
        'csrf_key' => 'csrf_token',
        'session_name' => 'TRXRHSESSID',
        'supervisor_email' => 'admin@seu-dominio.com.br',
        'supervisor_password' => 'troque-por-uma-senha-forte',
        'allowed_upload_mime' => ['application/pdf'],
        'max_upload_bytes' => 5 * 1024 * 1024,
        'allowed_image_mime' => ['image/png', 'image/jpeg', 'image/webp'],
        'max_image_bytes' => 2 * 1024 * 1024
    ],
    'mail' => [
        'enabled' => true,
        'from' => 'no-reply@seu-dominio.com.br',
        'to_hr' => 'rh@seu-dominio.com.br',
        'subject_new_application' => 'Nova candidatura recebida',
        'subject_password_recovery' => 'Recuperação de senha',
        'subject_password_changed' => 'Senha redefinida',
        'subject_supervisor_created' => 'Usuário Supervisor criado'
    ],
    'logging' => [
        'level' => 'INFO',
        'alert_email' => '',
        'viewer_key' => ''
    ]
];
