<?php

namespace App\Http\Controllers\API;

use App\Models\Berita;
use App\Models\Tag;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\Berita\BeritaCollection;
use App\Http\Resources\Komentar\KomentarBeritaCollection;
use Carbon\Carbon;

class BeritaApiController extends Controller
{
    // Menampilkan daftar berita dengan pagination & pencarian berdasarkan judul
    public function index(Request $request)
    {
        //   $query = Berita::where(function ($query) {
        //         $query->where('status_berita', 'Aktif')
        //               ->orWhereNull('status_berita'); // Jika NULL tetap ditampilkan
        //     });
        
        $query = Berita::query();

        // Jika request dari front-end (misalnya bukan admin), hanya tampilkan berita aktif
        if ($request->has('status_berita')) {
            $query->where('status_berita', $request->status_berita);
        }

        // Filter berdasarkan 'judul'
        if ($request->has('search')) {
            $query->where('judul', 'like', '%' . $request->search . '%');
        }

        // Pagination (default 10 per page)
        $berita = $query->latest()->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'message' => 'Daftar Berita',
            'data' => BeritaCollection::collection($berita),
            'pagination' => [
                'total' => $berita->total(),
                'per_page' => $berita->perPage(),
                'current_page' => $berita->currentPage(),
                'last_page' => $berita->lastPage(),
                'from' => $berita->firstItem(),
                'to' => $berita->lastItem()
            ]
        ], 200);
    }
    
    public function likeBerita($id)
    {
        $berita = Berita::find($id);
    
        if (!$berita) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], 404);
        }
    
        // Toggle Like (Jika sudah like, maka unlike)
        if (session()->has("liked_berita_$id")) {
            $berita->decrement('likes_berita');
            session()->forget("liked_berita_$id");
    
            return response()->json([
                'success' => true,
                'message' => 'Berita di-unlike',
                'likes' => $berita->likes_berita
            ]);
        }
    
        // Jika belum like, tambahkan like
        $berita->increment('likes_berita');
        session()->put("liked_berita_$id", true);
    
        return response()->json([
            'success' => true,
            'message' => 'Berita di-like',
            'likes' => $berita->likes_berita
        ]);
    }
    
    public function toggleStatus($id)
    {
        $berita = Berita::find($id);
    
        if (!$berita) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], 404);
        }
    
        // Toggle status berita antara "Aktif" dan "Tidak Aktif"
        $berita->status_berita = $berita->status_berita === Berita::STATUS_AKTIF 
            ? Berita::STATUS_NON_AKTIF 
            : Berita::STATUS_AKTIF;
        $berita->save();
    
        return response()->json([
            'success' => true,
            'message' => 'Status berita berhasil diperbarui',
            'data' => [
                'id' => $berita->id_berita,
                'judul' => $berita->judul,
                'status_berita' => $berita->status_berita
            ]
        ], 200);
    }

    
    public function counting()
    {
        $totalBerita = Berita::count(); // Menghitung jumlah total berita
    
        return response()->json([
            'success' => true,
            'message' => 'Total Berita',
            'total_berita' => $totalBerita
        ], 200);
    }

    
    public function indexall(Request $request)
    {
        $query = Berita::query();

        // Filter berdasarkan 'judul' jika ada parameter pencarian
        if ($request->has('search')) {
            $query->where('judul', 'like', '%' . $request->search . '%');
        }

        // Ambil semua data berita tanpa pagination
        $berita = $query->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Berita',
            'data' => BeritaCollection::collection($berita),
        ], 200);
    }

    // Menampilkan berita berdasarkan ID
    public function show($id)
    {
        // Cari berita berdasarkan ID
        $berita = Berita::find($id);
    
        // Jika berita tidak ditemukan, kembalikan error 404
        if (!$berita) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], 404);
        }
    
        // Increment the views count
        // $berita = Berita::incrementViews($id);
    
        // Jika ditemukan, kembalikan data berita
        return response()->json([
            'success' => true,
            'message' => 'Detail Berita',
            'data' => new BeritaCollection($berita)
        ], 200);
    }
    
    public function showJudul($judul)
    {
        // Cari berita berdasarkan judul
        $berita = Berita::where('judul', '=', urldecode($judul))->first();
    
        // Jika berita tidak ditemukan, kembalikan error 404
        if (!$berita) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], 404);
        }
        
        // Increment views pada berita yang ditemukan
        $berita = Berita::incrementViews($judul);
    
        // Jika berita tidak ditemukan setelah increment
        if (!$berita) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambah views'
            ], 500);
        }
    
        // Jika ditemukan, kembalikan data berita
        return response()->json([
            'success' => true,
            'message' => 'Detail Berita',
            'data' => new BeritaCollection($berita)
        ], 200);
    }

    // Menyimpan berita baru
    public function store(Request $request)
    {
        // Validasi input
        $request->validate([
            'judul' => 'required|string|max:255',
            'konten' => 'required|string',
            'tanggal' => 'required|date',
            'id_kategori_berita' => 'nullable|exists:kategori_berita,id',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto2' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto3' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
              // Validasi untuk tags; asumsikan tags dikirim sebagai array
            'tags'               => 'nullable|array',
            'tags.*.nama'        => 'required_with:tags|string|max:255',
            'tags.*.link'        => 'nullable|url',
        ]);
        
          // Jika input tanggal tidak menyertakan waktu, tambahkan waktu sekarang
        $tanggal = Carbon::parse($request->tanggal);
        if ($tanggal->format('H:i:s') === '00:00:00') {
            $tanggal->setTimeFromTimeString(Carbon::now()->format('H:i:s')); // Ambil jam saat ini jika tidak ada
        }
    
        // Buat berita baru di database
        $berita = Berita::create([
            'judul' => $request->judul,
            'konten' => $request->konten,
            'tanggal' => $tanggal->toDateTimeString(), // Simpan dalam format database
            'id_kategori_berita' => $request->id_kategori_berita,
            'status_berita' => Berita::STATUS_AKTIF,
        ]);

        // Folder penyimpanan
        $folderPath = "berita/{$berita->id_berita}";

        // Simpan foto jika ada
        if ($request->hasFile('foto')) {
            $fileName = $request->file('foto')->getClientOriginalName();
            $request->file('foto')->storeAs($folderPath, $fileName, 'public');
            $berita->update(['foto' => $fileName]); // Simpan hanya nama file
        }

        if ($request->hasFile('foto2')) {
            $fileName = $request->file('foto2')->getClientOriginalName();
            $request->file('foto2')->storeAs($folderPath, $fileName, 'public');
            $berita->update(['foto2' => $fileName]);
        }

        if ($request->hasFile('foto3')) {
            $fileName = $request->file('foto3')->getClientOriginalName();
            $request->file('foto3')->storeAs($folderPath, $fileName, 'public');
            $berita->update(['foto3' => $fileName]);
        }
        
        // Proses menyimpan tag jika ada input 'tags'
        if ($request->has('tags')) {
            $tagIds = [];
            foreach ($request->tags as $tagData) {
                // Gunakan firstOrCreate agar jika tag sudah ada, langsung gunakan id tag tersebut
                $tag = Tag::firstOrCreate(
                    ['nama' => $tagData['nama']],
                    ['link' => $tagData['link'] ?? null]
                );
                $tagIds[] = $tag->id;
            }
            // Hubungkan berita dengan tag melalui relasi many-to-many
            $berita->tags()->sync($tagIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berita berhasil ditambahkan',
            'data'    => new BeritaCollection($berita->load(['kategori', 'tags'])),
        ], 201);
    }
    
    public function beritaJudulKategori($judul, $id)
    {
        // Decode judul karena mungkin ada spasi atau karakter khusus
        $decodedJudul = urldecode($judul);
        
        // Cari berita berdasarkan judul dengan eager load relasi
        $berita = Berita::with([
            'komentar' => function($query) {
                $query->whereNull('parent_id')->latest();
            },
            'komentar.replies.replies.replies',
            'kategori'
        ])
        ->where('judul', $decodedJudul)
        ->first();
        
        if (!$berita) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan.'
            ], 404);
        }
        
        // Cek apakah berita tersebut termasuk kategori yang dimaksud
        if ($berita->id_kategori_berita != $id) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan pada kategori yang dimaksud.'
            ], 404);
        }
        
        // Increment views tanpa mengubah instance eager loaded
        Berita::where('id_berita', $berita->id_berita)->increment('views_berita');
        // Secara opsional, Anda bisa juga merefresh instance jika perlu menampilkan data views terbaru, 
        // tapi jika tidak, instance $berita tetap membawa relasi yang sudah dimuat.
        
        // Hitung jumlah komentar (misalnya hanya komentar utama)
        $commentCount = $berita->komentar()->count();
        
        return response()->json([
            'success' => true,
            'data'    => [
                'id'            => $berita->id_berita,
                'konten'        => $berita->konten,
                'foto'          => $berita->foto_url,
                'foto2'         => $berita->foto2_url,
                'foto3'         => $berita->foto3_url,
                'judul'         => $berita->judul,
                'tanggal'       => $berita->tanggal,
                // Karena kita tidak refresh instance, views_berita masih dari $berita (mungkin belum ter-update)
                // Jika diinginkan tampilan terbaru, Anda bisa mengupdate properti secara manual:
                'views_berita'  => $berita->views_berita + 1,
                'likes_berita'  => $berita->likes_berita,
                'comment_count' => $commentCount,
                'comments'      => KomentarBeritaCollection::collection($berita->komentar),
                'status_berita' => $berita->status_berita,
                'kategori'      => $berita->kategori ? [
                    'id'            => $berita->kategori->id,
                    'name_kategori' => $berita->kategori->name_kategori,
                ] : null,
                'tags'          => $berita->tags->map(function ($tag) {
                    return [
                        'id'   => $tag->id,
                        'nama' => $tag->nama,
                        'link' => $tag->link,
                    ];
                })->all(),
            ]
        ], 200);
    }
    
    public function komentarByIdBerita($id)
    {
        $berita = Berita::with([
            'komentar' => function($query) {
                $query->whereNull('parent_id')->latest();
            },
            'komentar.replies.replies.replies'
        ])->find($id);
    
        if (!$berita) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan.'
            ], 404);
        }
    
        return response()->json([
            'success' => true,
            'data'    => KomentarBeritaCollection::collection($berita->komentar)
        ]);
    }



    public function beritaTerbaruByKategori($id)
    {
        // Ambil berita terbaru berdasarkan kategori, dengan eager load relasi komentar (komentar utama beserta reply-nya)
        $berita = Berita::with([
                        'komentar' => function($query) {
                            // Hanya ambil komentar utama (parent_id null); 
                            // jika Anda ingin memuat nested reply, pastikan relasi replies sudah di-define
                            $query->whereNull('parent_id')->latest();
                        },
                        // Eager load nested replies hingga tiga tingkat
                        'komentar.replies.replies.replies'
                    ])
                    ->where('id_kategori_berita', $id)
                    ->where('status_berita', Berita::STATUS_AKTIF)
                    ->orderBy('tanggal', 'desc')
                    ->first();
    
        if ($berita) {
            // Hitung jumlah komentar (hanya dari komentar utama atau seluruh komentar, sesuaikan kebutuhan)
            $commentCount = $berita->komentar()->count();
    
            return response()->json([
                'success' => true,
                'data' => [
                    'id'             => $berita->id_berita,
                    'konten'         => $berita->konten,
                    'foto'           => $berita->foto_url,
                    'foto2'          => $berita->foto2_url,
                    'foto3'          => $berita->foto3_url,
                    'judul'          => $berita->judul,
                    'tanggal'        => $berita->tanggal,
                    'views_berita'   => $berita->views_berita,
                    'likes_berita'   => $berita->likes_berita,
                    'comment_count'  => $commentCount, // jumlah komentar
                    // Mengembalikan detail komentar melalui resource collection (pastikan resource sudah di-setup)
                    'comments'       => KomentarBeritaCollection::collection($berita->komentar),
                    'status_berita'  => $berita->status_berita,
                    'kategori'       => $berita->kategori ? [
                                            'id'            => $berita->kategori->id,
                                            'name_kategori' => $berita->kategori->name_kategori,
                                        ] : null,
                    'tags'          => $berita->tags->map(function ($tag) {
                        return [
                            'id'   => $tag->id,
                            'nama' => $tag->nama,
                            'link' => $tag->link,
                        ];
                    })->all(),
                ]
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan untuk kategori ini'
            ], 404);
        }
    }


    
    public function beritaTerbaruByKategoriAll(Request $request, $id)
    {
        // Ambil parameter per_page dan page, dengan default per_page = 10 dan page = 1
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
    
        // Ambil seluruh berita aktif berdasarkan kategori dengan pagination, diurutkan dari terbaru ke terlama
        $berita = Berita::where('id_kategori_berita', $id)
                        ->where('status_berita', Berita::STATUS_AKTIF)
                        ->orderBy('tanggal', 'desc')
                        ->paginate($perPage, ['*'], 'page', $page);
    
        if ($berita->count() > 0) {
            // Mapping data untuk response
            $data = $berita->getCollection()->map(function($b) {
                return [
                    'id'            => $b->id_berita,
                    'konten'        => $b->konten,
                    'foto'          => $b->foto_url,
                    'foto2'         => $b->foto2_url,
                    'foto3'         => $b->foto3_url,
                    'judul'         => $b->judul,
                    'tanggal'       => $b->tanggal,
                    'views_berita'  => $b->views_berita,
                    'likes_berita'  => $b->likes_berita,
                    'status_berita' => $b->status_berita,
                    'kategori'      => $b->kategori ? [
                        'id'            => $b->kategori->id,
                        'name_kategori' => $b->kategori->name_kategori,
                    ] : null, 
                   'tags'          => $b->tags->map(function ($tag) {
                    return [
                        'id'   => $tag->id,
                        'nama' => $tag->nama,
                        'link' => $tag->link,
                    ];
                })->all(),
                ];
            });
    
            return response()->json([
                'success'    => true,
                'data'       => $data,
                'pagination' => [
                    'total'        => $berita->total(),
                    'per_page'     => $berita->perPage(),
                    'current_page' => $berita->currentPage(),
                    'last_page'    => $berita->lastPage(),
                ]
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan untuk kategori ini'
            ], 404);
        }
    }


    public function update(Request $request, $id)
    {
        // Cari berita berdasarkan ID
        $berita = Berita::find($id);
    
        if (!$berita) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], 404);
        }
    
        // Validasi input
        $request->validate([
            'judul' => 'required|string|max:255',
            'konten' => 'required|string',
            'tanggal' => 'required|date',
             'id_kategori_berita' => 'nullable|exists:kategori_berita,id',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto2' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto3' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'tags'               => 'nullable|array',
            'tags.*.nama'        => 'required_with:tags|string|max:255',
            'tags.*.link'        => 'nullable|url',
        ]);
        
         $tanggal = Carbon::parse($request->tanggal);
        if ($tanggal->format('H:i:s') === '00:00:00') {
            $tanggal->setTimeFromTimeString(Carbon::now()->format('H:i:s')); // Ambil jam saat ini jika tidak ada
        }
    
        // Update data berita
        $berita->update([
            'judul' => $request->judul,
            'konten' => $request->konten,
            'tanggal' => $tanggal->toDateTimeString(), 
            'id_kategori_berita' => $request->id_kategori_berita, 
        ]);
    
        // Folder penyimpanan
        $folderPath = 'berita/' . $berita->id_berita;
    
        // Fungsi untuk update foto
        function updateFoto($request, $berita, $fieldName, $folderPath)
        {
            if ($request->hasFile($fieldName)) {
                // Hapus foto lama jika ada
                if (!empty($berita->$fieldName)) {
                    Storage::disk('public')->delete($folderPath . '/' . $berita->$fieldName);
                }
                // Simpan foto baru
                $fileName = $request->file($fieldName)->getClientOriginalName();
                $request->file($fieldName)->storeAs($folderPath, $fileName, 'public');
                $berita->update([$fieldName => $fileName]);
            }
        }
    
        // Proses update foto
        updateFoto($request, $berita, 'foto', $folderPath);
        updateFoto($request, $berita, 'foto2', $folderPath);
        updateFoto($request, $berita, 'foto3', $folderPath);
        
         if ($request->has('tags')) {
            $tagIds = [];
            foreach ($request->tags as $tagData) {
                // Gunakan firstOrCreate untuk menghindari duplikasi data tag
                $tag = \App\Models\Tag::firstOrCreate(
                    ['nama' => $tagData['nama']],
                    ['link' => $tagData['link'] ?? null]
                );
                $tagIds[] = $tag->id;
            }
            // Sinkronisasikan tag-tag yang terkait dengan berita
            $berita->tags()->sync($tagIds);
        } else {
            // Jika tidak ada input tags, Anda bisa mengosongkan relasi tags
            // $berita->tags()->detach();
        }
    
        return response()->json([
            'success' => true,
            'message' => 'Berita berhasil diperbarui',
            'data'    => $berita->load(['kategori', 'tags'])
        ], 200);
    }

    public function destroy($id)
    {
        // Cari berita berdasarkan ID
        $berita = Berita::find($id);
    
        if (!$berita) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], 404);
        }
    
        // Path folder tempat gambar disimpan
        $folderPath = 'berita/' . $berita->id_berita;
    
        // Fungsi untuk menghapus gambar
        function deleteFoto($berita, $fieldName, $folderPath)
        {
            if (!empty($berita->$fieldName)) {
                Storage::disk('public')->delete($folderPath . '/' . $berita->$fieldName);
            }
        }
    
        // Hapus semua gambar jika ada
        deleteFoto($berita, 'foto', $folderPath);
        deleteFoto($berita, 'foto2', $folderPath);
        deleteFoto($berita, 'foto3', $folderPath);
    
        // Hapus folder berita jika sudah kosong
        Storage::disk('public')->deleteDirectory($folderPath);
    
        // Hapus berita dari database
        $berita->delete();
    
        return response()->json([
            'success' => true,
            'message' => 'Berita berhasil dihapus'
        ], 200);
    }
}
