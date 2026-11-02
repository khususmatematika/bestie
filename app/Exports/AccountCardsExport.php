<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Kartu akun berisi kata sandi awal. Berkas ini sensitif: hanya dapat diunduh
 * satu kali oleh Admin, lalu otomatis dihapus dari server.
 */
final class AccountCardsExport extends StringValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithTitle
{
    /**
     * @param  list<array{name: string, email: string|null, nip: string|null, password: string}>  $accounts
     */
    public function __construct(private readonly array $accounts)
    {
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Nama', 'Email', 'NIP', 'Masuk dengan', 'Kata sandi awal', 'Alamat aplikasi'];
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return array_map(
            static fn (array $account): array => [
                $account['name'],
                (string) ($account['email'] ?? ''),
                (string) ($account['nip'] ?? ''),
                (string) (($account['nip'] ?? '') !== '' ? $account['nip'] : $account['email']),
                $account['password'],
                url('/login'),
            ],
            $this->accounts,
        );
    }

    public function title(): string
    {
        return 'Kartu Akun';
    }
}
