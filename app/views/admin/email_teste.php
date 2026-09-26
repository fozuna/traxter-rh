<div class="responsive-panel max-w-2xl">
  <h2 class="text-2xl font-semibold text-ctpblue">Teste de e-mail</h2>
  <p class="mt-2 text-gray-600">Envia uma mensagem de teste usando a mesma configuração dos avisos de candidatura e da recuperação de senha.</p>

  <?php if (!empty($resultado)): ?>
    <?php if ($resultado['ok']): ?>
      <div class="mt-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded" role="status">
        <strong>Enviado</strong> via <?= Security::e($resultado['metodo']) ?>.
        Confira a caixa de entrada <em>e o spam</em> de <?= Security::e($destino) ?>.
        <?php if (str_starts_with($resultado['metodo'], 'mail()')): ?>
          <p class="mt-2 text-sm">Observação: com <code>mail()</code>, "enviado" só significa que o servidor aceitou a mensagem. Se não chegar em alguns minutos, configure o SMTP.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded" role="alert">
        <strong>Falhou</strong> (<?= Security::e($resultado['metodo']) ?>): <?= Security::e($resultado['erro']) ?>
      </div>
    <?php endif; ?>
    <p class="mt-2 text-sm text-gray-500">Remetente configurado: <?= Security::e($resultado['from']) ?> · Avisos de RH para: <?= Security::e($resultado['to_hr']) ?></p>
  <?php endif; ?>

  <form class="mt-6 flex flex-col sm:flex-row gap-3" method="post" action="<?= $base ?>/admin/email-teste">
    <input type="hidden" name="csrf" value="<?= Security::e($csrf) ?>">
    <input type="email" name="destino" required value="<?= Security::e($destino) ?>" class="border rounded px-3 py-2 w-full" aria-label="Enviar para">
    <button type="submit" class="bg-ctgreen text-white px-4 py-2 rounded hover:bg-ctdark whitespace-nowrap">Enviar teste</button>
  </form>

  <div class="mt-8 text-sm text-gray-600 border-t pt-4">
    <p class="font-semibold text-ctpblue">Configuração recomendada (Hostinger)</p>
    <p class="mt-1">No <code>config.php</code>, dentro de <code>'mail'</code>, use a caixa do remetente criada no hPanel:</p>
    <pre class="mt-2 bg-gray-100 p-3 rounded overflow-x-auto text-xs">'smtp' => [
    'host' => 'smtp.hostinger.com',
    'port' => 465,
    'secure' => 'ssl',
    'user' => 'no-reply@seudominio.com.br',
    'pass' => 'SENHA-DA-CAIXA',
],</pre>
  </div>
</div>
