<?php
/**
 * Identidade visual da instalação (por cliente).
 * Tudo vem das seções 'cliente' e 'suporte' do app/config/config.php.
 */
class Brand
{
    private const DEFAULT_LOGO = 'assets/logo.png';

    private const DEFAULT_COLORS = [
        'escuro' => '#0d1321',
        'medio'  => '#1d2d44',
        'claro'  => '#3e5c76',
    ];

    /** Nome da empresa cliente (portal de vagas, títulos, exportações). */
    public static function clientName(): string
    {
        $name = trim((string)(self::client()['nome'] ?? ''));
        return $name !== '' ? $name : self::productName();
    }

    /** Nome do produto (ex.: TRAXTER RH). */
    public static function productName(): string
    {
        return (string)(Config::get()['app']['name'] ?? 'TRAXTER RH');
    }

    /** Site institucional do cliente (opcional). */
    public static function clientSite(): string
    {
        $site = trim((string)(self::client()['site'] ?? ''));
        return preg_match('#^https?://#i', $site) ? $site : '';
    }

    /** Há logo próprio do cliente configurado e presente em disco? */
    public static function hasClientLogo(): bool
    {
        return self::clientLogoPath() !== null;
    }

    /** URL do logo para fundo escuro: do cliente, se houver; senão o do produto. */
    public static function logoUrl(string $base): string
    {
        $path = self::clientLogoPath() ?? self::DEFAULT_LOGO;
        return rtrim($base, '/') . '/' . $path;
    }

    /** Texto alternativo padrão para o logo. */
    public static function logoAlt(): string
    {
        return self::hasClientLogo() ? self::clientName() : self::productName() . ' - Recrutamento e Seleção';
    }

    /** Título da aba do navegador. */
    public static function pageTitle(string $section = ''): string
    {
        $parts = array_filter([$section, self::clientName()]);
        if (self::clientName() !== self::productName()) {
            $parts[] = self::productName();
        }
        return implode(' | ', $parts);
    }

    /** Cores normalizadas (#rrggbb), com padrão para valores ausentes ou inválidos. */
    public static function colors(): array
    {
        $cfg = (array)(self::client()['cores'] ?? []);
        $out = [];
        foreach (self::DEFAULT_COLORS as $key => $default) {
            $value = strtolower(trim((string)($cfg[$key] ?? '')));
            $out[$key] = preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : $default;
        }
        return $out;
    }

    /** Bloco <style> com as variáveis de cor usadas pelo Tailwind e pelos componentes. */
    public static function styleTag(): string
    {
        $c = self::colors();
        [$r, $g, $b] = sscanf($c['claro'], '#%02x%02x%02x');
        return '<style>:root{'
            . '--ctdark:' . $c['escuro'] . ';'
            . '--ctpblue:' . $c['escuro'] . ';'
            . '--ctgreen:' . $c['medio'] . ';'
            . '--ctlight:' . $c['claro'] . ';'
            . "--ctlight-rgb:{$r},{$g},{$b};"
            . '}</style>';
    }

    /** Contato de suporte exibido no manual. */
    public static function support(): array
    {
        $s = (array)(Config::get()['suporte'] ?? []);
        $digits = preg_replace('/\D+/', '', (string)($s['whatsapp'] ?? ''));
        return [
            'nome'     => (string)($s['nome'] ?? 'TRAXTER Sistemas e Automações'),
            'site'     => (string)($s['site'] ?? 'https://traxter.com.br/'),
            'whatsapp' => $digits,
            'whatsapp_url' => $digits !== '' ? 'https://wa.me/' . $digits : '',
            'whatsapp_label' => self::formatPhone($digits),
        ];
    }

    private static function formatPhone(string $digits): string
    {
        if (strlen($digits) === 13) {
            return sprintf('(%s) %s-%s', substr($digits, 2, 2), substr($digits, 4, 5), substr($digits, 9));
        }
        return $digits;
    }

    private static function client(): array
    {
        return (array)(Config::get()['cliente'] ?? []);
    }

    /** Caminho relativo do logo do cliente, validado (sem ../, extensão de imagem, arquivo existente). */
    private static function clientLogoPath(): ?string
    {
        $path = ltrim(str_replace('\\', '/', trim((string)(self::client()['logo'] ?? ''))), '/');
        if ($path === '' || str_contains($path, '..') || !preg_match('/\.(png|jpe?g|webp|svg)$/i', $path)) {
            return null;
        }
        return is_file(BASE_PATH . '/' . $path) ? $path : null;
    }
}
