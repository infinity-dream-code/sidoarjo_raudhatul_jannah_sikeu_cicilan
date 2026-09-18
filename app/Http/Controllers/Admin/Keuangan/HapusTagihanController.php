<?php

namespace App\Http\Controllers\Admin\Keuangan;

use App\Http\Controllers\Controller;
use App\Models\mst_kelas;
use App\Models\mst_tagihan;
use App\Models\mst_thn_aka;
use App\Models\scctbill;
use App\Models\scctbill_detail;
use App\Models\scctcust;
use App\Models\ValidationMessage;
use App\Support\SchoolScope;
use App\Support\TagihanPaymentReversal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HapusTagihanController extends Controller
{
    public ?string $sekolah = null;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $this->sekolah = Auth::user()->sekolah;
            }

            return $next($request);
        });

        $this->title = 'Hapus Tagihan Siswa';
        $this->datasUrl = route('admin.keuangan.hapus-tagihan.get-data');
        $this->columnsUrl = route('admin.keuangan.hapus-tagihan.get-column');
    }

    public function index()
    {
        $data['title'] = $this->title;
        $data['columnsUrl'] = $this->columnsUrl;
        $data['datasUrl'] = $this->datasUrl;
        $data['post'] = mst_tagihan::select(['tagihan'])->get();
        $data['thn_aka'] = mst_thn_aka::select(['thn_aka'])->where('thn_aka', '!=', null)->get();
        $data['periode'] = scctbill::query()
            ->whereNotNull('BILLAC')
            ->where('BILLAC', '!=', '')
            ->distinct()
            ->orderBy('BILLAC', 'desc')
            ->pluck('BILLAC');
        $data['kelas'] = mst_kelas::dropdownQuery($this->sekolah)
            ->orderByRaw("CASE WHEN kelas REGEXP '^[0-9]+$' THEN 0 ELSE 1 END, kelas")
            ->get();

        return view('admin.keuangan.hapus_tagihan', $data);
    }

    public function getColumn()
    {
        return [
            ['data' => 'AA', 'name' => 'no', 'columnType' => 'row'],
            ['data' => 'nocust', 'name' => 'NIS', 'searchable' => true, 'orderable' => true],
            ['data' => 'nmcust', 'name' => 'NAMA', 'searchable' => true, 'orderable' => true],
            ['data' => 'CODE02', 'name' => 'Unit', 'searchable' => true, 'orderable' => true],
            ['data' => 'DESC02', 'name' => 'Kelas', 'searchable' => true, 'orderable' => true],
            ['data' => 'DESC03', 'name' => 'Kelompok', 'searchable' => true, 'orderable' => true],
            ['data' => 'BILLNM', 'name' => 'Nama Tagihan', 'searchable' => true, 'orderable' => true],
            ['data' => 'nominal', 'name' => 'Nominal', 'searchable' => false, 'orderable' => true, 'columnType' => 'currency', 'className' => 'text-end'],
            ['data' => 'BILLAC', 'name' => 'Periode', 'searchable' => true, 'orderable' => true],
        ];
    }

    public function getData(Request $request)
    {
        try {
            return $this->buildGetDataResponse($request);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('hapus-tagihan.get-data.failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'draw' => (int) $request->get('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function buildGetDataResponse(Request $request)
    {
        $draw = $request->get('draw');
        $start = max(0, (int) $request->get('start', 0));
        $rowperpage = (int) $request->get('length', 10);
        if ($rowperpage < 1) {
            $rowperpage = 10;
        }
        $columnName_arr = $request->get('columns', []);
        $search_arr = $request->get('search', []);

        $defaultColumn = 'scctbill.PAIDDT';
        $defaultOrder = 'desc';
        $columnName = $defaultColumn;
        $columnSortOrder = $defaultOrder;
        $searchValue = $search_arr['value'] ?? '';

        $orderArr = $request->get('order');
        $sortable = [
            'nocust' => 'scctcust.nocust',
            'nmcust' => 'scctcust.nmcust',
            'CODE02' => 'scctcust.CODE02',
            'DESC02' => 'scctcust.DESC02',
            'DESC03' => 'scctcust.DESC03',
            'BILLNM' => 'scctbill.BILLNM',
            'BILLAC' => 'scctbill.BILLAC',
            'AA' => 'scctbill.AA',
            'nominal' => 'scctbill.BILLAM',
        ];
        if (is_array($orderArr) && $orderArr !== [] && is_array($columnName_arr) && $columnName_arr !== []) {
            $columnIndex = (int) ($orderArr[0]['column'] ?? -1);
            $requested = $columnName_arr[$columnIndex]['data'] ?? null;
            if ($requested && isset($sortable[$requested])) {
                $columnName = $sortable[$requested];
                $columnSortOrder = strtolower((string) ($orderArr[0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
            }
        }

        $filters = [];
        $filterQuery = null;

        $filter = $request->input('filter');
        if ($filter) {
            foreach ($filter as $key => $val) {
                if (strtolower($val) != 'all' && $val !== null && $val !== '') {
                    $colName = match ($key) {
                        'tanggal-pembuatan' => 'scctbill.FTGLTagihan',
                        'periode' => 'scctbill.BILLAC',
                        'post' => 'scctbill.BILLNM',
                        'siswa' => 'scctcust.nmcust',
                        default => null
                    };
                    if ($key == 'tanggal-pembuatan') {
                        if (preg_match('/^\d{2}-\d{2}-\d{4} [-\/~] \d{2}-\d{2}-\d{4}$/', $val)) {
                            $val = preg_replace('/[-\/~]/', '-', $val);

                            list($startDate, $endDate) = explode(' - ', $val);
                            $startDate = Carbon::createFromFormat('d-m-Y', $startDate)->startOfDay();
                            $endDate = Carbon::createFromFormat('d-m-Y', $endDate)->endOfDay();
                            if ($startDate && $endDate) {
                                ($colName) && $filters[] = [$colName, $startDate, $endDate, 'whereBetween'];
                            }
                        }
                    } elseif ($key == 'siswa') {
                        $val = is_numeric($val) ? $val : '%' . $val . '%';
                        $colName = is_numeric($val) ? 'scctcust.nocust' : $colName;
                        ($colName) && $filters[] = [$colName, 'like', $val];
                    } elseif ($key == 'kelas') {
                        $parts = explode('~~', (string) $val);
                        if (count($parts) === 3) {
                            $filters[] = ['scctcust.CODE02', '=', $parts[0]];
                            $filters[] = ['scctcust.DESC02', '=', $parts[1]];
                            $filters[] = ['scctcust.DESC03', '=', $parts[2]];
                        }
                    } elseif ($key == 'angkatan') {
                        $filters[] = ['scctcust.DESC04', '=', $val];
                    } else {
                        ($colName) && $filters[] = [$colName, '=', $val];
                    }
                }
            };

            if (!empty($filters)) {
                $filterQuery = function ($query) use ($filters) {
                    foreach ($filters as $filter) {
                        if (count($filter) === 3) {
                            $query->where($filter[0], $filter[1], $filter[2]);
                        } elseif (count($filter) === 4) {
                            if ($filter[3] == 'whereBetween') {
                                $query->whereBetween($filter[0], [$filter[1], $filter[2]]);
                            } else {
                                $query->{$filter[3]}($filter[0], $filter[1], $filter[2]);
                            }
                        }
                    }
                };
            }
        }

        $whereAny = [
            'scctcust.nmcust',
            'scctcust.nocust',
        ];

        $detailSum = scctbill_detail::query()
            ->select([
                'CUSTID',
                'BILLCD',
                DB::raw('COALESCE(SUM(BILLAM), 0) AS nominal_detail'),
            ])
            ->groupBy('CUSTID', 'BILLCD');

        $select = array_unique(array_merge($whereAny, [
            'scctbill.AA',
            'scctbill.BILLNM',
            'scctbill.BILLAM',
            'scctbill.BILLCD',
            'scctbill.PAIDST',
            'scctbill.PAIDDT',
            'scctbill.BILLAC',
            'scctbill.FIDBANK',
            'scctbill.FUrutan',
            'scctcust.CODE02',
            'scctcust.DESC02',
            'scctcust.DESC03',
            'scctcust.CUSTID',
        ]));

        $query = scctbill::leftJoin('scctcust', 'scctcust.CUSTID', 'scctbill.CUSTID')
            ->leftJoinSub($detailSum, 'bill_detail', function ($join) {
                $join->on('bill_detail.CUSTID', '=', 'scctbill.CUSTID')
                    ->on('bill_detail.BILLCD', '=', 'scctbill.BILLCD');
            })
            ->where('scctbill.PAIDST', 0)
            ->where('scctbill.FSTSBolehBayar', 1)
            ->whereRaw('CAST(COALESCE(scctcust.STCUST, 0) AS SIGNED) = 1');

        SchoolScope::apply($query, 'scctcust', $this->sekolah);

        $query->when(!blank($searchValue), function ($query) use ($whereAny, $searchValue) {
                $query->where(function ($q) use ($whereAny, $searchValue) {
                    $sanitizeSearch = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $searchValue);
                    foreach ($whereAny as $column) {
                        $q->orWhere($column, 'like', '%' .$sanitizeSearch . '%');
                    }
                });
            })
            ->where(function ($query) use ($filterQuery) {
                if ($filterQuery) {
                    $filterQuery($query);
                }
            });

        $scopeKey = blank($this->sekolah) ? 'all' : $this->sekolah;
        $totalRecords = Cache::remember('total_tagihan_count_' . $scopeKey, 600, function () {
            return scctbill::query()
                ->leftJoin('scctcust', 'scctcust.CUSTID', '=', 'scctbill.CUSTID')
                ->where('scctbill.FSTSBolehBayar', 1)
                ->where('scctbill.PAIDST', 0)
                ->when($this->sekolah, fn ($q) => $q->where('scctcust.CODE01', $this->sekolah))
                ->count();
        });

        $totalRecordswithFilter = (clone $query)->count();

        $records = (clone $query)->orderBy($columnName, $columnSortOrder)
            ->select($select)
            ->addSelect(DB::raw('COALESCE(NULLIF(bill_detail.nominal_detail, 0), scctbill.BILLAM) AS nominal'))
            ->skip($start)
            ->take($rowperpage)
            ->get()
            ->map(function ($item) {
                $item->delete = true;
                $item->item_id = $item->AA;
                $item->CUSTID = $item->CUSTID;
                $item->nocust = $item->nocust ?? $item->NOCUST ?? '';
                $item->nmcust = $item->nmcust ?? $item->NMCUST ?? '';
                $item->billam = $item->nominal;
                return $item;
            })->toArray();
        $response = array(
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords ?? 0,
            "recordsFiltered" => $totalRecordswithFilter ?? 0,
            "data" => $records ?? [],
        );
        return response()->json($response);
    }

    public function destroy($id, Request $request)
    {
        $custId = $request->input('user_id') ?? $request->input('custid');

        $tagihan = scctbill::where('AA', $id)
            ->where('FSTSBolehBayar', '=', 1)
            ->where('PAIDST', '=', 0)
            ->when($custId, fn ($q) => $q->where('CUSTID', $custId))
            ->first();

        if (!$tagihan) {
            return response()->json(['message' => 'Tagihan tidak ditemukan!'], 422);
        }

        $siswa = scctcust::where('CUSTID', $tagihan->CUSTID)->first();
        if (!$siswa) {
            return response()->json(['message' => 'Siswa tidak ditemukan!'], 422);
        }

        try {
            app(TagihanPaymentReversal::class)->deleteUnpaidTagihan($tagihan, $request);

            return response()->json(['message' => 'Tagihan dihapus!'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal Menghapus Tagihan!',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'AA' => ['required', 'array'],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes()
        );

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            if ($validator->errors()->count() > 1) {
                $message = "{$message} Dan beberapa error lainnya untuk update";
            }

            return response()->json(
                [
                    "message" => $message,
                    "errors" => $validator->errors(),
                ],
                422
            );
        }

        try {
            $reversal = app(TagihanPaymentReversal::class);
            $tagihans = scctbill::query()
                ->whereIn('AA', $request->AA)
                ->where('FSTSBolehBayar', 1)
                ->where('PAIDST', 0)
                ->get();

            foreach ($tagihans as $tagihan) {
                $reversal->deleteUnpaidTagihan($tagihan, $request);
            }

            return response()->json(['message' => 'Tagihan dihapus!'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal Menghapus Tagihan!',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
