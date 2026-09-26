<?php
?>
<div class="bg-white shadow rounded p-6">
  <a href="<?= $base ?>/vagas" class="text-sm text-ctpblue hover:text-ctgreen">← Voltar</a>
  <h2 class="mt-2 text-xl font-semibold text-ctpblue"><?= Security::e($vaga['titulo']) ?></h2>

  <!-- Área superior dividida em dois containers -->
  <div class="mt-4 grid md:grid-cols-2 gap-6">
    <!-- Container 1: Informações completas da vaga -->
    <div class="border rounded p-4">
      <h3 class="font-medium text-ctpblue">Informações da Vaga</h3>
      <p class="mt-2 text-gray-700"><?= nl2br(Security::e($vaga['descricao'])) ?></p>
      <p class="mt-2 text-gray-600"><strong>Requisitos:</strong> <?= nl2br(Security::e($vaga['requisitos'])) ?></p>
      <p class="mt-2 text-gray-600">Área: <?= Security::e($vaga['area']) ?> • Local: <?= Security::e($vaga['local']) ?></p>
    </div>

    <!-- Container 2: Benefícios em grade de duas colunas -->
    <div class="border rounded p-4">
      <h3 class="font-medium text-ctpblue">Benefícios</h3>
      <?php if (!empty($beneficios)): ?>
        <div class="mt-2 grid grid-cols-2 gap-3">
          <?php foreach ($beneficios as $b): ?>
            <div class="text-sm bg-gray-50 border rounded p-3">
              <?php if (!empty($b['logo_path'])): ?>
                <img src="<?= $base ?>/uploads/logos/<?= Security::e($b['logo_path']) ?>" alt="Logo <?= Security::e($b['parceiro'] ?? $b['nome']) ?>" class="h-10 w-auto object-contain mb-2" />
              <?php endif; ?>
              <div class="font-medium text-ctpblue flex items-center">
                <span><?= Security::e($b['nome']) ?></span>
                <?php if (!empty($b['parceiro'])): ?>
                  <span class="text-gray-500 ml-1">• <?= Security::e($b['parceiro']) ?></span>
                <?php endif; ?>
              </div>
              <?php if (!empty($b['descricao'])): ?>
                <div class="text-gray-600 mt-1"><?= Security::e($b['descricao']) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="mt-2 text-gray-500">Nenhum benefício ativo no momento.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="mt-6">
    <h3 class="font-medium text-ctpblue">Formulário de Candidatura</h3>
    <?php $old = $old ?? []; $maxMb = (int)round(((int)(Config::app()['security']['max_upload_bytes'] ?? 5242880)) / 1048576); ?>
    <?php if (!empty($erro)): ?>
      <div id="form-erro" class="mt-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded" role="alert">
        <?= Security::e($erro) ?>
      </div>
    <?php endif; ?>
    <form class="mt-3 space-y-4" action="<?= $base ?>/candidatar/<?= (int)$vaga['id'] ?>" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= Security::e($csrf) ?>">
      <div>
        <label class="block text-sm font-medium text-ctpblue">Nome</label>
        <input type="text" name="nome" value="<?= Security::e($old['nome'] ?? '') ?>" required class="mt-1 w-full border rounded px-3 py-2 shadow-sm focus:border-ctgreen focus:ring-1 focus:ring-ctgreen" />
      </div>
      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-ctpblue">E-mail</label>
          <input type="email" name="email" value="<?= Security::e($old['email'] ?? '') ?>" required class="mt-1 w-full border rounded px-3 py-2 shadow-sm focus:border-ctgreen focus:ring-1 focus:ring-ctgreen" />
        </div>
        <div>
          <label class="block text-sm font-medium text-ctpblue">Telefone</label>
          <input type="tel" name="telefone" value="<?= Security::e($old['telefone'] ?? '') ?>" required data-phone-input="1" maxlength="15" placeholder="(00) 00000-0000" class="mt-1 w-full border rounded px-3 py-2 shadow-sm focus:border-ctgreen focus:ring-1 focus:ring-ctgreen" />
          <div data-phone-error="invalid" class="text-red-600 text-sm mt-1 hidden">Telefone inválido. Informe 11 dígitos (DDD + número).</div>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-ctpblue flex items-center">
          CPF 
          <span class="ml-1 text-gray-400 cursor-help" title="Precisamos do seu CPF para evitar candidaturas duplicadas e garantir a integridade do processo seletivo">ℹ️</span>
        </label>
        <input type="text" name="cpf" id="cpf" maxlength="14" value="<?= Security::e($old['cpf'] ?? '') ?>" required data-cpf-input="1"
               class="mt-1 w-full border rounded px-3 py-2 shadow-sm focus:border-ctgreen focus:ring-1 focus:ring-ctgreen" 
               placeholder="000.000.000-00" />
        <div id="cpf-error" data-cpf-error="exists" class="text-red-600 text-sm mt-1 hidden">Você já se candidatou a esta vaga recentemente.</div>
        <div id="cpf-invalid" data-cpf-error="invalid" class="text-red-600 text-sm mt-1 hidden">CPF inválido. Verifique os dígitos.</div>
      </div>
      <div>
        <label class="block text-sm font-medium text-ctpblue">Cargo pretendido</label>
        <input type="text" name="cargo_pretendido_display" value="<?= Security::e($vaga['titulo']) ?>" class="mt-1 w-full border rounded px-3 py-2 bg-gray-100 text-gray-600 cursor-not-allowed" disabled />
        <input type="hidden" name="cargo_pretendido" value="<?= Security::e($vaga['titulo']) ?>" />
      </div>
      <div>
        <label class="block text-sm font-medium text-ctpblue">Experiência</label>
        <textarea name="experiencia" rows="4" class="mt-1 w-full border rounded px-3 py-2 shadow-sm focus:border-ctgreen focus:ring-1 focus:ring-ctgreen" required><?= Security::e($old['experiencia'] ?? '') ?></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-ctpblue">Currículo (PDF, até <?= $maxMb ?> MB)</label>
        <input type="file" name="curriculo" accept="application/pdf" required class="mt-1 w-full" />
      </div>
      <div class="flex items-start gap-2">
        <input type="checkbox" name="lgpd_aceite" id="lgpd_aceite" value="1" required class="mt-1 h-4 w-4 shrink-0">
        <label for="lgpd_aceite" class="text-sm text-gray-700">
          Li e aceito a <a href="<?= $base ?>/privacidade" target="_blank" rel="noopener" class="underline text-ctgreen">Política de Privacidade</a>
          e autorizo o uso dos meus dados pessoais e do meu currículo por <strong><?= Security::e(Brand::clientName()) ?></strong>
          para participar deste e de outros processos seletivos.
        </label>
      </div>
      <button type="submit" class="bg-ctgreen text-white px-4 py-2 rounded hover:bg-ctdark">Enviar candidatura</button>
    </form>
  </div>
</div>
