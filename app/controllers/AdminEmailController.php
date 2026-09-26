<?php
/** Diagnóstico de envio de e-mail (somente administradores). */
class AdminEmailController extends Controller
{
    public function index(): void
    {
        Auth::requireRole(['admin']);
        $user = User::findById((int)($_SESSION['user_id'] ?? 0));
        $this->view->render('admin/email_teste', [
            'csrf' => Security::csrfToken(),
            'destino' => $user->email ?? '',
            'resultado' => null,
        ], 'layouts/admin');
    }

    public function send(): void
    {
        Auth::requireRole(['admin']);
        if (!Security::csrfCheck($_POST['csrf'] ?? '')) {
            http_response_code(400);
            echo 'Falha na verificação de segurança (CSRF).';
            return;
        }
        $destino = trim((string)($_POST['destino'] ?? ''));
        $ok = Mailer::sendTo(
            $destino,
            'Teste de e-mail - ' . Brand::clientName(),
            "Este é um e-mail de teste do " . Brand::productName() . ".\n\n"
            . "Se você recebeu esta mensagem, os avisos de candidatura e a recuperação de senha vão funcionar.\n\n"
            . 'Enviado em ' . date('d/m/Y H:i:s') . '.'
        );
        $cfg = Config::app()['mail'];
        $this->view->render('admin/email_teste', [
            'csrf' => Security::csrfToken(),
            'destino' => $destino,
            'resultado' => [
                'ok' => $ok,
                'metodo' => Mailer::lastMethod(),
                'erro' => Mailer::lastError(),
                'from' => (string)($cfg['from'] ?? ''),
                'to_hr' => (string)($cfg['to_hr'] ?? ''),
            ],
        ], 'layouts/admin');
    }
}
