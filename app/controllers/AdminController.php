<?php
class AdminController extends Controller
{
    public function index(): void
    {
        Auth::requireRole(['admin', 'rh', 'viewer']);
        $vagasAtivas = count(Vaga::allActive());
        $allCandidaturas = Candidatura::all();
        $totalCandidaturas = count($allCandidaturas);
        
        $stages = PipelineStage::all();
        $stats = [];
        foreach ($stages as $st) {
            $stats[$st['nome']] = 0;
        }
        
        // Calculate counts per stage
        foreach ($allCandidaturas as $c) {
            $stageName = $c['stage_nome'] ?? 'Desconhecido';
            if (isset($stats[$stageName])) {
                $stats[$stageName]++;
            } else {
                $stats[$stageName] = 1;
            }
        }
        
        try {
            $alertas = Alertas::pendencias(10);
        } catch (\Throwable $e) {
            Logger::warning('Falha ao calcular alertas', ['erro' => $e->getMessage()]);
            $alertas = ['nao_triadas' => [], 'paradas' => []];
        }
        $this->view->render('admin/dashboard', [
            'alertas' => $alertas,
            'diasTriagem' => Alertas::diasTriagem(),
            'diasParado' => Alertas::diasParado(),
            'vagasAtivas' => $vagasAtivas,
            'totalCandidaturas' => $totalCandidaturas,
            'stats' => $stats,
            'stages' => $stages,
        ], 'layouts/admin');
    }
}