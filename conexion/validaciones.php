<?php
// ============================================================
// validaciones.php
// Reglas compartidas de validación de nombres de clientes.
// Se usa desde registro.php (auto-registro) y desde
// clientes_dashboard/guardar_cliente.php (alta manual), para
// que ambos flujos apliquen el MISMO criterio.
// ============================================================

/**
 * Valida un "nombre (y apellido)" mediante heurísticas:
 *  - cada palabra (nombre/apellido) con longitud mínima y máxima
 *  - cada palabra es realista: tiene al menos una vocal
 *  - sin símbolos raros ni caracteres repetidos (aaaaa, 1111)
 *
 * @param string $nombre Texto tal como lo escribió el usuario.
 * @return array Lista de errores (vacía = válido).
 */
function validarNombreApellido(string $nombre): array
{
    $errores = [];

    $nombre = trim($nombre);

    if ($nombre === '' || mb_strlen($nombre, 'UTF-8') < 2) {
        $errores[] = 'El nombre debe tener entre 2 y 50 caracteres.';
        return $errores;
    }

    if (mb_strlen($nombre, 'UTF-8') > 50) {
        $errores[] = 'El nombre debe tener entre 2 y 50 caracteres.';
        return $errores;
    }

    if (!preg_match('/^[\p{L}\p{M} .\'\-]+$/u', $nombre)) {
        $errores[] = 'El nombre solo puede contener letras y espacios.';
        return $errores;
    }

    // Cada palabra (nombre, apellido, compuestos como "María Luisa")
    $palabras = preg_split('/\s+/', $nombre);

    // No permitir más de, digamos, 4 palabras completas sueltas
    if (count($palabras) > 4) {
        $errores[] = 'El nombre tiene demasiadas palabras.';
        return $errores;
    }

    foreach ($palabras as $palabra) {
        $palabra = trim($palabra);

        // Longitud mínima realista por palabra: evita "lu", "an", "f"
        // (apodos cortos; el filtro final lo pone la dueña al aprobar)
        if (mb_strlen($palabra, 'UTF-8') < 3) {
            $errores[] = 'Cada parte del nombre debe tener al menos 3 letras.';
            return $errores;
        }

        // Nombre "real" = necesita al menos una vocal (incluye tildes)
        if (!preg_match('/[aeiouáéíóúü]/iu', $palabra)) {
            $errores[] = 'Las partes del nombre deben incluir vocales.';
            return $errores;
        }

        // Rechazar caracteres repetidos: "aaaaa", "11111", "jjjj"
        if (preg_match('/(.)\1{2,}/u', $palabra)) {
            $errores[] = 'El nombre no puede tener letras repetidas en bloque.';
            return $errores;
        }
    }

    // Palabras prohibidas dentro del nombre (pite, mierda, etc.)
    if (contienePalabraProhibida($nombre)) {
        $errores[] = 'El nombre contiene una palabra no permitida.';
    }

    return $errores;
}