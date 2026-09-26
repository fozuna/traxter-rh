<?php
class ApiController
{
    public function checkCpf(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $cpf = $input['cpf'] ?? '';

        // Remove formatação do CPF
        $cpf = preg_replace('/\D/', '', $cpf);

        if (!Security::isValidCpf($cpf)) {
            http_response_code(400);
            echo json_encode(['error' => 'CPF inválido']);
            return;
        }

        $vagaId = (int)($input['vaga_id'] ?? 0);
        if ($vagaId <= 0) {
            echo json_encode(['exists' => false]);
            return;
        }
        $liberaEm = Candidatura::bloqueioReaplicacao($cpf, $vagaId);
        echo json_encode([
            'exists' => $liberaEm !== null,
            'libera_em' => $liberaEm !== null ? date('d/m/Y', strtotime($liberaEm)) : null,
        ]);
    }
}