<div class="mb-6">
    <h1 class="text-fluid-title font-bold text-gray-800">Dashboard</h1>
    <p class="text-fluid-subtitle text-gray-600">Visão geral do recrutamento</p>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
    <div class="responsive-panel flex flex-col gap-4 border-l-4 border-ctgreen sm:flex-row sm:items-center">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-ctlight text-white">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">Vagas Ativas</p>
            <p class="text-2xl font-bold text-gray-800"><?= $vagasAtivas ?></p>
        </div>
    </div>

    <div class="responsive-panel flex flex-col gap-4 border-l-4 border-ctgreen sm:flex-row sm:items-center">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-ctlight text-white">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">Total de Candidaturas</p>
            <p class="text-2xl font-bold text-gray-800"><?= $totalCandidaturas ?></p>
        </div>
    </div>
</div>

<?php
  $alertas = $alertas ?? ['nao_triadas' => [], 'paradas' => []];
  $blocos = [
      ['titulo' => 'Aguardando triagem', 'dica' => 'na primeira etapa há mais de ' . (int)($diasTriagem ?? 3) . ' dias', 'itens' => $alertas['nao_triadas'], 'cor' => 'border-amber-400', 'badge' => 'bg-amber-100 text-amber-800'],
      ['titulo' => 'Paradas na etapa', 'dica' => 'sem movimentação há mais de ' . (int)($diasParado ?? 7) . ' dias', 'itens' => $alertas['paradas'], 'cor' => 'border-red-400', 'badge' => 'bg-red-100 text-red-800'],
  ];
  $totalAlertas = count($alertas['nao_triadas']) + count($alertas['paradas']);
?>
<div class="responsive-panel mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Precisa de atenção</h2>
        <?php if ($totalAlertas === 0): ?>
            <span class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-full px-3 py-1">Tudo em dia</span>
        <?php endif; ?>
    </div>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <?php foreach ($blocos as $b): ?>
        <div class="rounded-xl border-l-4 <?= $b['cor'] ?> bg-gray-50 p-4">
            <div class="flex items-baseline justify-between gap-2">
                <p class="font-semibold text-gray-800"><?= Security::e($b['titulo']) ?></p>
                <span class="text-xs font-semibold rounded-full px-2 py-0.5 <?= $b['badge'] ?>"><?= count($b['itens']) ?><?= count($b['itens']) >= 10 ? '+' : '' ?></span>
            </div>
            <p class="text-xs text-gray-500 mb-3"><?= Security::e($b['dica']) ?></p>
            <?php if (empty($b['itens'])): ?>
                <p class="text-sm text-gray-500">Nenhuma candidatura nesta situação.</p>
            <?php else: ?>
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($b['itens'] as $it): ?>
                    <li class="py-2 flex items-center justify-between gap-3">
                        <a href="<?= $base ?>/admin/candidaturas/<?= (int)$it['id'] ?>" class="min-w-0">
                            <span class="block truncate font-medium text-ctpblue hover:underline"><?= Security::e($it['nome']) ?></span>
                            <span class="block truncate text-xs text-gray-500"><?= Security::e((string)$it['vaga_titulo']) ?> · <?= Security::e((string)$it['etapa']) ?></span>
                        </a>
                        <span class="shrink-0 text-xs font-semibold text-gray-700"><?= (int)$it['dias'] ?> dias</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="responsive-panel mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Pipeline de Seleção</h2>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <?php foreach ($stages as $st): ?>
            <div class="rounded-xl border-t-4 bg-gray-50 p-4 text-center" style="border-color: <?= $st['cor'] ?>">
                <p class="text-sm font-medium text-gray-500"><?= htmlspecialchars($st['nome']) ?></p>
                <p class="text-xl font-bold text-gray-800 mt-1"><?= $stats[$st['nome']] ?? 0 ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-6 flex justify-start sm:justify-end">
        <a href="<?= $base ?>/admin/pipeline" class="inline-flex items-center justify-center rounded-lg bg-ctgreen px-4 py-3 text-sm font-medium text-white hover:bg-ctdark">Ver Quadro Kanban Completo</a>
    </div>
</div>
