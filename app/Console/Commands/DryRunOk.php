<?php

namespace App\Console\Commands;

use RuntimeException;

/**
 * Señal interna para deshacer la transacción de un `--dry-run` sin marcar
 * error: el sembrador valida el banco entero dentro de la transacción y la
 * revienta a propósito. La usan `dialogos:sembrar` y `vocabulario:sembrar`.
 */
class DryRunOk extends RuntimeException {}
