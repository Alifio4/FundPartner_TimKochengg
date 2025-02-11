<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Usaha;
use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;
use Illuminate\Support\Facades\Storage;


class MitraController extends Controller
{
    public function index()
    {
        $data = Usaha::all();
        $data2 = Usaha::where('status', 'Belum didanai')->orderBy('created_at', 'desc')->take(8)->get();
        $data3 = User::where('role', '0')->count();
        $data4 = Usaha::where('status', 'Didanai')->count();
        $data5 = Usaha::count();
        $data6 = Usaha::where('status', 'Didanai')->get();
        $data7 = User::where('role', '1')->count();

        

        // return view("mitra.index",$data,$data2);
        return view('mitra.index')->with('usaha', $data)->with('usaha2', $data2)->with('usaha3', $data3)
            ->with('usaha4', $data4)->with('usaha5', $data5)->with('usaha6', $data6)->with('usaha7', $data7);
    }

    public function create()
    {
        $userID = Auth::id();
        $umkm = Usaha::with('usaha')->where('id_mitra', $userID)->where('status', 'Belum didanai')->count();
        $umkm2 = Usaha::with('usaha')->where('id_mitra', $userID)->where('status', 'didanai')->count();

    return view("usaha.formUsaha")->with('usaha',$umkm)->with('usaha2',$umkm2);
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'nama_usaha' => ['required', 'string', 'max:100', 'unique:' . Usaha::class],
            'id_mitra' => ['required', 'integer'],
            'deskripsi' => ['required', 'string'],
            'dana' => ['required', 'integer'],
            'waktu' => ['required', 'integer'],
            'pembayaran' => ['required', 'string'],
            'gambar' => ['required', File::types(['jpg', 'jpeg', 'png', 'gif', 'jfif'])->max(12 * 1024)],
        ]);

        $path = Storage::putFile('gambar', $request->file('gambar'));
        $usaha = new Usaha;
        $usaha->id_mitra = $request->id_mitra;
        $usaha->nama_usaha = $request->nama_usaha;
        $usaha->deskripsi = $request->input('deskripsi');
        $usaha->dana = $request->dana;
        $usaha->waktu = $request->waktu;
        $usaha->pembayaran = $request->pembayaran;
        $usaha->gambar = $path;
        $usaha->save();
        $request->session()->flash('success', 'Usaha berhasil ditambahkan');

        // $details = Usaha::with('usaha')->where('id_mitra', $request->id_mitra)->get();
        // return view('usaha.detailUsaha', compact('details'));
        return redirect()->route('rincianInvestment');
    }

    function tagihan($id)
    {
        //GENERATE PEMBAYARAN
        //Fungsi ini akan menggenerate pembayaran sesuai dengan waktu fungsi ini digunakan, dan mengubah status usaha menjadi didanai
        //karena fungsi ini hanya digunakan ketika investor menekan tombol bayar
        //kurangnya yaitu menambahkan id investor dan mentransfer saldo investor ke mitra
        DB::transaction(function () use ($id){


            $usaha = Usaha::find($id);
            $investor = User::find(Auth::id());
            $mitra = User::find($usaha->id_mitra);
            if ($usaha->pembayaran == 'lunas') {
                $pelunasan = $usaha->dana * 1.1; //Menghitung pelunasan ,1.1 = 10% keuntungan
                $tempo = Carbon::now()->addDays(7 * $usaha->waktu);
                $usaha->status = 'didanai'; //Mengubah status menjadi didanai
                $usaha->id_investor = Auth::id(); //Mengisi id_investor
                $usaha->save();
                $mitra->saldo = $mitra->saldo + $usaha->dana; //Mentransfer saldo investor ke mitra
                $mitra->save();
                $investor->saldo = $investor->saldo - $usaha->dana; //Mengurangi saldo investor
                $investor->save();
                $newPayment = new Pembayaran;
                $newPayment->id_mitra = $usaha->id; //Mengisi id_mitra
                $newPayment->jumlah_pembayaran = $pelunasan;
                $newPayment->status = false;
                $newPayment->jenis_pembayaran = $usaha->pembayaran;
                $newPayment->tanggal_jatuh_tempo = $tempo;
                $newPayment->save();
                session()->flash('success', 'Usaha berhasil didanai');
                // return redirect()->route('indexinvestor');
            } else {
                $pelunasan = (($usaha->dana * 1.1) / $usaha->waktu);
                $tempo = Carbon::now()->addDays(7);
                // dd($usaha->id);
                for ($i = 1; $i <= $usaha->waktu; $i++) {
                    $newPayment = new Pembayaran;
                    $newPayment->id_mitra = $usaha->id;
                    $usaha->status = 'didanai'; //Mengubah status menjadi didanai
                    $usaha->id_investor = Auth::id(); //Mengisi id_investor
                    $usaha->save();
                    $mitra->saldo = $mitra->saldo + $usaha->dana; //Mentransfer saldo investor ke mitra
                    $mitra->save();
                    $investor->saldo = $investor->saldo - $usaha->dana; //Mengurangi saldo investor
                    $investor->save();
                    $newPayment->jumlah_pembayaran = $pelunasan;
                    $newPayment->status = false;
                    $newPayment->jenis_pembayaran = $usaha->pembayaran;
                    $newPayment->tanggal_jatuh_tempo = $tempo;
                    $newPayment->save();
                    $tempo = $tempo->addDays(7);
                    session()->flash('success', 'Usaha berhasil didanai');
                };
                // return redirect()->route('indexinvestor');
            };
        });
        // return redirect()->route('indexinvestor');
        $details = Usaha::with('usaha')->where('id', $id)->get();
        return view('usaha.detailUsaha', compact('details'));
    }
}
