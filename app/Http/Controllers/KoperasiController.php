<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // Untuk logging jika ada error
use Web3\Web3;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use App\Models\Pinjaman; // CONTOH: Jika Anda menggunakan DB untuk event indexing
use Web3\RequestManagers\HttpRequestManager;

class KoperasiController extends Controller
{
    protected $web3;
    protected $koperasiContract;
    protected $tokenContract;

    /**
     * Constructor untuk menginisialisasi koneksi ke blockchain dan kontrak.
     * Ini akan berjalan setiap kali controller dipanggil.
     */
    public function __construct()
    {
        $requestManager = new HttpRequestManager(config('services.infura.sepolia_url'), 5);
        $this->web3 = new Web3(new HttpProvider($requestManager));

        // === BAGIAN YANG DIPERBAIKI ===
        // 1. Baca file ABI sebagai string
        $koperasiAbiString = file_get_contents(storage_path('app/abis/KoperasiSimpanPinjam.json'));
        $tokenAbiString = file_get_contents(storage_path('app/abis/IDRToken.json'));

        // 2. Decode string JSON menjadi array/object PHP untuk membersihkannya
        $koperasiAbi = json_decode($koperasiAbiString);
        $tokenAbi = json_decode($tokenAbiString);
        // ============================

        // 3. Gunakan ABI yang sudah bersih untuk membuat instance kontrak
        $this->koperasiContract = new Contract($this->web3->provider, $koperasiAbi);
        $this->tokenContract = new Contract($this->web3->provider, $tokenAbi);
    }


    /**
     * Mengambil informasi umum dari kontrak koperasi.
     * Endpoint ini cocok untuk halaman dashboard.
     */
    public function getContractInfo()
    {
        try {
            $jumlahAnggota = null;
            $sukuBunga = null;

            // Memanggil variabel publik 'jumlahAnggota' dari smart contract
            $this->koperasiContract->at(config('services.infura.koperasi_address'))->call('jumlahAnggota', function ($err, $result) use (&$jumlahAnggota) {
                if ($err !== null) {
                    throw new \Exception($err->getMessage());
                }
                $jumlahAnggota = $result[0]->toString();
            });

            // Memanggil variabel publik 'sukuBungaPersen'
            $this->koperasiContract->at(config('services.infura.koperasi_address'))->call('sukuBungaPersen', function ($err, $result) use (&$sukuBunga) {
                if ($err !== null) {
                    throw new \Exception($err->getMessage());
                }
                $sukuBunga = $result[0]->toString();
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'koperasi_address' => config('services.infura.koperasi_address'),
                    'token_address' => config('services.infura.token_address'),
                    'jumlah_anggota' => $jumlahAnggota,
                    'suku_bunga_persen' => $sukuBunga,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data dari kontrak.'], 500);
        }
    }

    /**
     * Mengambil detail spesifik dari seorang anggota berdasarkan alamat wallet.
     */
    public function getAnggotaDetail($alamat)
    {
        try {
            $anggotaData = null;

            // Memanggil mapping publik 'dataAnggota' dengan parameter alamat
            $this->koperasiContract->at(config('services.infura.koperasi_address'))->call('dataAnggota', $alamat, function ($err, $result) use (&$anggotaData) {
                if ($err !== null) {
                    throw new \Exception($err->getMessage());
                }
                $anggotaData = $result;
            });

            // Jika anggota tidak ditemukan (terdaftar == false)
            if (!$anggotaData[0]) {
                 return response()->json(['success' => false, 'message' => 'Anggota tidak ditemukan.'], 404);
            }

            // Format data agar lebih mudah dibaca oleh frontend
            $formattedData = [
                'terdaftar' => $anggotaData[0],
                'nama' => $anggotaData[1],
                'simpanan_pokok' => $anggotaData[2]->toString(),
                'simpanan_wajib' => $anggotaData[3]->toString(),
                'simpanan_sukarela' => $anggotaData[4]->toString(),
                'memiliki_pinjaman_aktif' => $anggotaData[5],
            ];
            
            return response()->json(['success' => true, 'data' => $formattedData]);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data anggota.'], 500);
        }
    }

    /**
     * CONTOH LANJUTAN: Mengambil semua data pinjaman dari DATABASE LOKAL.
     * Ini adalah cara yang direkomendasikan untuk data list agar tidak lambat.
     * Diasumsikan Anda sudah memiliki event listener yang mengisi tabel 'pinjamans'.
     */
    public function getAllPinjaman()
    {
        // Logika ini membaca dari database MySQL/PostgreSQL Anda, bukan dari blockchain secara langsung.
        // Ini membuat response API menjadi sangat cepat.
        $pinjamanList = Pinjaman::orderBy('id', 'desc')->get();

        return response()->json(['success' => true, 'data' => $pinjamanList]);
    }
}