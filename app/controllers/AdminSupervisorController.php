<?php
class AdminSupervisorController extends Controller
{
    public function ensure(): void
    {
        Auth::requireRole(['admin']);
        SchemaManager::ensure();
        if (!Security::csrfCheck($_POST['csrf'] ?? '')) {
            http_response_code(400);
            echo 'Falha na verificação de segurança (CSRF).';
            return;
        }

        $cfg = Config::app();
        $supervisorEmail = trim((string)($cfg['security']['supervisor_email'] ?? ''));
        $actor = User::findById((int)($_SESSION['user_id'] ?? 0));

        if (!filter_var($supervisorEmail, FILTER_VALIDATE_EMAIL)) {
            http_response_code(500);
            echo 'Configuração de supervisor inválida: defina security.supervisor_email no config.php.';
            return;
        }

        $result = User::ensureSupervisorAccount('Supervisor', $supervisorEmail);
        $id = $result['id'];
        AuditLog::log($actor?->id, $id, 'supervisor_ensured', $result['created'] ? 'Usuário supervisor criado' : 'Usuário supervisor garantido', Security::clientIp());

        $subject = $cfg['mail']['subject_supervisor_created'] ?? 'Usuário Supervisor criado';
        if ($result['created']) {
            // Senha nunca trafega nem fica no config: o supervisor define a própria senha pelo link
            $rawToken = bin2hex(random_bytes(32));
            PasswordReset::create($id, $rawToken, 1440);
            $link = rtrim((string)($cfg['base_url'] ?? ''), '/') . '/admin/reset-password/' . urlencode($rawToken);
            $message = "O usuário Supervisor foi criado com privilégios irrestritos.\n\n"
                . "Defina a senha pelo link abaixo (válido por 24 horas):\n{$link}\n\n"
                . "Se o link expirar, use \"Esqueci minha senha\" na tela de login.";
        } else {
            $message = "O usuário Supervisor foi verificado. Permissões restauradas, senha mantida.\nE-mail: {$supervisorEmail}";
        }
        Mailer::sendTo($supervisorEmail, $subject, $message);
        Mailer::notifyHR($subject, "Operação no usuário Supervisor ({$supervisorEmail}) executada por um administrador.");

        redirect('/admin/usuarios/novo?supervisor=ok');
    }
}
