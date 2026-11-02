<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan alur (mis. paket sudah terkunci, catatan terlalu pendek).
 * Pesannya berbahasa Indonesia dan ditampilkan langsung ke pengguna.
 */
final class WorkflowException extends RuntimeException
{
}
