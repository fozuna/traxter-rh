<?php
/** Política de Privacidade do portal de vagas (texto modelo, parametrizado pelo config do cliente). */
$empresa = Brand::clientName();
$cnpj = Brand::clientCnpj();
$emailPrivacidade = Brand::privacyEmail();
$retencao = Brand::retentionMonths();
$operador = Brand::support()['nome'];
$versao = Consentimento::VERSAO_POLITICA;
?>
<article class="bg-white rounded-lg shadow-sm p-6 md:p-10 max-w-3xl mx-auto text-gray-700 leading-relaxed">
  <h2 class="text-2xl font-semibold text-ctpblue">Política de Privacidade — Candidatos</h2>
  <p class="text-sm text-gray-500 mt-1">Versão <?= Security::e($versao) ?></p>

  <p class="mt-6">
    Esta política explica como <strong><?= Security::e($empresa) ?></strong><?= $cnpj !== '' ? ', CNPJ ' . Security::e($cnpj) . ',' : '' ?>
    trata os dados pessoais de quem se candidata às vagas publicadas neste portal, conforme a
    Lei Geral de Proteção de Dados (Lei nº 13.709/2018 — LGPD).
  </p>

  <h3 class="text-lg font-semibold text-ctpblue mt-8">1. Quem é responsável pelos seus dados</h3>
  <p class="mt-2">
    <strong><?= Security::e($empresa) ?></strong> é a <em>controladora</em>: decide como e para que seus dados são usados.
    A plataforma é fornecida por <strong><?= Security::e($operador) ?></strong>, que atua como <em>operadora</em>,
    tratando os dados apenas conforme as instruções da controladora.
  </p>

  <h3 class="text-lg font-semibold text-ctpblue mt-8">2. Quais dados coletamos</h3>
  <ul class="list-disc pl-6 mt-2 space-y-1">
    <li>Identificação e contato: nome, e-mail, telefone e CPF;</li>
    <li>Informações profissionais: cargo pretendido, experiência e o currículo enviado (com os dados que você incluir nele);</li>
    <li>Registro do aceite desta política: data e hora, endereço IP e navegador utilizado.</li>
  </ul>
  <p class="mt-2">
    Recomendamos <strong>não incluir no currículo</strong> dados sensíveis, como foto, religião, estado de saúde,
    orientação sexual, filiação sindical ou opinião política. Eles não são necessários para a seleção.
  </p>

  <h3 class="text-lg font-semibold text-ctpblue mt-8">3. Para que usamos seus dados</h3>
  <ul class="list-disc pl-6 mt-2 space-y-1">
    <li>Avaliar sua candidatura para esta vaga e para outras vagas compatíveis com seu perfil;</li>
    <li>Entrar em contato sobre as etapas do processo seletivo;</li>
    <li>Evitar candidaturas duplicadas (por isso pedimos o CPF);</li>
    <li>Manter registros necessários para cumprir obrigações legais e para a defesa de direitos.</li>
  </ul>
  <p class="mt-2">
    As bases legais são o seu <strong>consentimento</strong> (art. 7º, I), os <strong>procedimentos preliminares a um
    possível contrato de trabalho</strong> (art. 7º, V) e o <strong>exercício regular de direitos</strong> (art. 7º, VI).
  </p>

  <h3 class="text-lg font-semibold text-ctpblue mt-8">4. Com quem compartilhamos</h3>
  <p class="mt-2">
    Seus dados são acessados apenas pela equipe responsável pelo recrutamento de <?= Security::e($empresa) ?> e pelos
    fornecedores necessários ao funcionamento do portal (plataforma e hospedagem), sob obrigação contratual de
    confidencialidade. <strong>Não vendemos nem cedemos seus dados</strong> para fins de publicidade.
  </p>

  <h3 class="text-lg font-semibold text-ctpblue mt-8">5. Por quanto tempo guardamos</h3>
  <p class="mt-2">
    Mantemos seus dados por até <strong><?= (int)$retencao ?> meses</strong> após o encerramento do processo seletivo,
    para considerar você em novas oportunidades. Depois disso, eles são eliminados ou anonimizados, salvo quando a lei
    exigir guarda por mais tempo. Você pode pedir a exclusão antes desse prazo.
  </p>

  <h3 class="text-lg font-semibold text-ctpblue mt-8">6. Como protegemos</h3>
  <p class="mt-2">
    O acesso é restrito a usuários autorizados, com senha e registro de atividades. Os currículos ficam em área
    privada, sem acesso direto pela internet, e só podem ser baixados por usuários autenticados.
  </p>

  <h3 class="text-lg font-semibold text-ctpblue mt-8">7. Seus direitos</h3>
  <p class="mt-2">Conforme o art. 18 da LGPD, você pode, a qualquer momento:</p>
  <ul class="list-disc pl-6 mt-2 space-y-1">
    <li>confirmar se tratamos seus dados e acessá-los;</li>
    <li>corrigir dados incompletos, inexatos ou desatualizados;</li>
    <li>pedir a anonimização, o bloqueio ou a eliminação de dados;</li>
    <li>pedir a portabilidade dos seus dados;</li>
    <li>saber com quem seus dados foram compartilhados;</li>
    <li>revogar o consentimento, o que encerra sua participação nos processos seletivos.</li>
  </ul>
  <p class="mt-4">
    Para exercer seus direitos<?= $emailPrivacidade !== '' ? ', escreva para' : ', entre em contato com a empresa' ?>
    <?php if ($emailPrivacidade !== ''): ?>
      <a href="mailto:<?= Security::e($emailPrivacidade) ?>" class="underline text-ctgreen"><?= Security::e($emailPrivacidade) ?></a>
    <?php endif; ?>
    informando seu nome e CPF. Você também pode apresentar reclamação à Autoridade Nacional de Proteção de Dados (ANPD).
  </p>

  <h3 class="text-lg font-semibold text-ctpblue mt-8">8. Alterações</h3>
  <p class="mt-2">
    Esta política pode ser atualizada. A versão vigente fica sempre disponível nesta página, e a versão aceita por você
    fica registrada junto à sua candidatura.
  </p>

  <p class="mt-10">
    <a href="<?= $base ?>/vagas" class="inline-block bg-ctgreen text-white px-4 py-2 rounded hover:bg-ctdark">Voltar às vagas</a>
  </p>
</article>
