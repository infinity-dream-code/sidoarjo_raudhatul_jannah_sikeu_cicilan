<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Keuangan\TagihanSiswa\AktifasiPembayaranBankController;
use App\Models\scctcust;
use App\Models\scctva;

class CaraBayarController extends Controller
{
    public function show(string $token)
    {
        $va = scctva::query()
            ->where("SHARE_TOKEN", $token)
            ->where("STATUS", 1)
            ->first();

        if (!$va) {
            return response()->view("cara_bayar.invalid", [
                "message" => "Link cara bayar tidak valid atau sudah diganti dengan aktifasi baru.",
            ], 404);
        }

        $siswa = scctcust::where("CUSTID", $va->CUSTID)->first();
        $payload = app(AktifasiPembayaranBankController::class)->buildSharePayload($va, $siswa);

        return view("cara_bayar.show", $payload);
    }

    public function pdf(string $token)
    {
        return app(AktifasiPembayaranBankController::class)->pdfByToken($token);
    }
}
