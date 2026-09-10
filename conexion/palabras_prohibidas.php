<?php
// ============================================================
// palabras_prohibidas.php
// Filtro de lenguaje ofensivo en nombres de usuario (y texto libre).
//
// Estrategia (recomendada para el contexto):
//   1. Normalizar: minúsculas, sin tildes y sin leet-speak (0→o, 1→i, 3→e, 4→a, 5→s, 7→t, @→a, $→s...).
//   2. Quitar separadores (. - _ espacios) para detectar ofensas "partidas".
//   3. Comparar por SUB-CADENA: "mierda123" y "lamierda" también se detectan.
//
// Uso:
//   require __DIR__ . '/palabras_prohibidas.php';
//   if (contienePalabraProhibida($usuario)) { /* rechazar */ }
// ============================================================

/**
 * Lista de palabras a bloquear (en minúsculas). Modificá esta lista
 * libremente: el filtro normaliza el input antes de comparar.
 */
function palabrasProhibidas(): array
{
    return [
        'puta', 'puto', 'polla', 'coño', 'concha', 'pija', 'verga', 'pinga',
        'mierda', 'caca', 'culo', 'pedo', 'pene', 'vagina', 'tetas', 'senos',
        'culo', 'tarado', 'tarada', 'estupido', 'estupida', 'idiota', 'imbecil',
        'gilipollas', 'cabron', 'cabrona', 'hijo de puta', 'hijoputa', 'pendejo',
        'pendeja', 'maricon', 'maricona', 'gay', 'lesbiana', 'trolo', 'putita',
        'puteria', 'cojon', 'cojones', 'culero', 'culera', 'pinche',
        'pajero', 'pajera', 'paja', 'masturba', 'follar', 'coger', 'chingar',
        'verga', 'choto', 'chota', 'wey', 'guey', 'pendejada', 'baboso', 'babosa',
        'nefasto', 'nefasta', 'bruto', 'bruta', 'vago', 'vaga',
    ];
}

/**
 * Normaliza un texto para comparar contra la lista:
 * minúsculas, sin tildes, leet-speak resuelto y sin separadores.
 */
function normalizarTextoFiltro(string $texto): string
{
    $t = mb_strtolower($texto, 'UTF-8');

    // Quitar tildes (normalización NFKD quita los diacríticos combinables)
    $t = iconv('UTF-8', 'ASCII//TRANSLIT', $t);

    // Leet-speak: dígitos/símbolos que imitan letras
    $leet = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's',
        '7' => 't', '8' => 'b', '@' => 'a', '$' => 's', '!' => 'i',
        '|' => 'i', '¡' => 'i',
    ];
    $t = strtr($t, $leet);

    // Quitar separadores y signos de puntuación → "la.puta!" pasa a "laputa"
    $t = preg_replace('/[^a-z]/', '', $t);

    return $t;
}

/**
 * Devuelve true si el texto contiene (como subcadena) alguna palabra prohibida.
 */
function contienePalabraProhibida(string $texto): bool
{
    $normalizado = normalizarTextoFiltro($texto);
    if ($normalizado === '') {
        return false;
    }

    foreach (palabrasProhibidas() as $palabra) {
        $palabraN = normalizarTextoFiltro($palabra);
        if ($palabraN !== '' && str_contains($normalizado, $palabraN)) {
            return true;
        }
    }

    return false;
}