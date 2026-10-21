@extends('dashboard.layouts.app')
@section('title', $title)
@section('styles')
    <style>
        .modal-dialog-scrollable {
            height: auto !important;
        }
        /* ألوان التبويبات */
        .nav-tabs .nav-link {
            background-color: #f8f9fa; /* اللون الافتراضي */
            border: 1px solid #dee2e6; /* حدود */
            color: #495057; /* لون النص */
            transition: background-color 0.3s ease;
        }

        .nav-tabs .nav-link.active {
            background-color: #007bff; /* لون خاص للتبويب النشط */
            color: #fff; /* لون النص */
            border-color: #007bff; /* حدود */
        }

        /* ألوان إضافية لكل تبويب */
        #live-tab {
            background-color: #28a745; /* لون خاص لتبويب live */
            color: #fff;
        }

        #live-tab.active {
            background-color: #218838; /* لون أغمق للتبويب النشط */
        }

        #demo-tab {
            background-color: #ffc107; /* لون خاص لتبويب demo */
            color: #fff;
        }

        #demo-tab.active {
            background-color: #e0a800; /* لون أغمق للتبويب النشط */
        }

        /* تحسين الحدود */
        .nav-tabs .nav-link {
            border-radius: 5px; /* زوايا مستديرة */
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
@endsection
@section('breadcrumb')
    @foreach ($pages as $page)
        <li class="breadcrumb-item active" aria-current="page">
            <a href="{{ $page[1] }}">{{ $page[0] }}</a>
        </li>
    @endforeach
@endsection
@section('content')
    @include('dashboard.components.errors')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white d-flex align-items-center justify-content-between border-bottom">
                    <strong class="card-title">{{ $title }}</strong>
                </div>
                <div class="card-body">
                    <!-- Tabs Navigation -->
                    <ul class="nav nav-tabs" id="transactionTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="live-tab" data-bs-toggle="tab" href="#live" role="tab"
                                aria-controls="live" aria-selected="true">{{ transWord('Live Transactions') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="demo-tab" data-bs-toggle="tab" href="#demo" role="tab"
                                aria-controls="demo" aria-selected="false">{{ transWord('Demo Transactions') }}</a>
                        </li>
                    </ul>

                    <!-- Tabs Content -->
                    <div class="tab-content pt-3" id="transactionTabsContent">
                        <!-- Live Transactions Tab -->
                        <div class="tab-pane fade show active" id="live" role="tabpanel" aria-labelledby="live-tab">
                            <div class="table-responsive">
                                <table id="live-transactions" class="table table-bordered dt-responsive nowrap"
                                    style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>{{ transWord('User') }}</th>
                                            <th>{{ transWord('Amount') }}</th>
                                            <th>{{ transWord('Approved At') }}</th>
                                            <th>{{ transWord('Approved By') }}</th>
                                            <th>{{ transWord('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($liveTransactions as $index => $value)
                                            @if ($value->user != null)
                                                <tr class="row_{{ $value->id }}">
                                                    <td>{{ $index + 1 }}</td>
                                                    <td>{{ $value->user->name }}</td>
                                                    <td>{{ $value->amount }}</td>
                                                    <td>{{ $value->approve_at }}</td>
                                                    <td>{{ optional($value->approvedBy)->name }}</td>
                                                    <td>
                                                        <button type="button"
                                                                class="btn btn-info btn-sm"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#detailsModal{{ $value->id }}">
                                                            {{ transWord('View Details') }}
                                                        </button>
                                                    </td>
                                                </tr>
                                                <div class="modal fade" id="detailsModal{{ $value->id }}" tabindex="-1">
                                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                        <div class="modal-content">

                                                            <div class="modal-header">
                                                                <h5 class="modal-title">
                                                                    {{ transWord('Withdrawal Details') }} - #{{ $value->id }}
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>

                                                            <div class="modal-body">
                                                                <div class="row g-3">

                                                                    <div class="col-md-6">
                                                                        <strong>User:</strong>
                                                                        <div>{{ optional($value->user)->name }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Amount:</strong>
                                                                        <div>{{ $value->amount }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Currency:</strong>
                                                                        <div>{{ $value->currency ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Payment Method:</strong>
                                                                        <div>{{ $value->payment_method ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Account Name:</strong>
                                                                        <div>{{ $value->account_name ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Account Phone:</strong>
                                                                        <div>{{ $value->account_phone ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Bank Name:</strong>
                                                                        <div>{{ $value->bank_name ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Bank Account:</strong>
                                                                        <div>{{ $value->bank_account ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Binance ID:</strong>
                                                                        <div>{{ $value->binanceId ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-12">
                                                                        <strong>Note:</strong>
                                                                        <div>{{ $value->note ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Approved At:</strong>
                                                                        <div>{{ $value->approve_at }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Approved By:</strong>
                                                                        <div>{{ optional($value->approvedBy)->name }}</div>
                                                                    </div>

                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Demo Transactions Tab -->
                        <div class="tab-pane fade" id="demo" role="tabpanel" aria-labelledby="demo-tab">
                            <div class="table-responsive">
                                <table id="demo-transactions" class="table table-bordered dt-responsive nowrap"
                                    style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>{{ transWord('User') }}</th>
                                            <th>{{ transWord('Amount') }}</th>
                                            <th>{{ transWord('Approved At') }}</th>
                                            <th>{{ transWord('Approved By') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($demoTransactions as $index => $value)
                                            @if ($value->user != null)
                                                <tr class="row_{{ $value->id }}">
                                                    <td>{{ $index + 1 }}</td>
                                                    <td>{{ $value->user->name }}</td>
                                                    <td>{{ $value->amount }}</td>
                                                    <td>{{ $value->approve_at }}</td>
                                                    <td>{{ optional($value->approvedBy)->name }}</td>
                                                    <td>
                                                        <button type="button"
                                                                class="btn btn-info btn-sm"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#detailsModal{{ $value->id }}">
                                                            {{ transWord('View Details') }}
                                                        </button>
                                                    </td>
                                                </tr>
                                                <div class="modal fade" id="detailsModal{{ $value->id }}" tabindex="-1">
                                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                        <div class="modal-content">

                                                            <div class="modal-header">
                                                                <h5 class="modal-title">
                                                                    {{ transWord('Withdrawal Details') }} - #{{ $value->id }}
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>

                                                            <div class="modal-body">
                                                                <div class="row g-3">

                                                                    <div class="col-md-6">
                                                                        <strong>User:</strong>
                                                                        <div>{{ optional($value->user)->name }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Amount:</strong>
                                                                        <div>{{ $value->amount }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Currency:</strong>
                                                                        <div>{{ $value->currency ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Payment Method:</strong>
                                                                        <div>{{ $value->payment_method ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Account Name:</strong>
                                                                        <div>{{ $value->account_name ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Account Phone:</strong>
                                                                        <div>{{ $value->account_phone ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Bank Name:</strong>
                                                                        <div>{{ $value->bank_name ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Bank Account:</strong>
                                                                        <div>{{ $value->bank_account ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Binance ID:</strong>
                                                                        <div>{{ $value->binanceId ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-12">
                                                                        <strong>Note:</strong>
                                                                        <div>{{ $value->note ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Approved At:</strong>
                                                                        <div>{{ $value->approve_at }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>Approved By:</strong>
                                                                        <div>{{ optional($value->approvedBy)->name }}</div>
                                                                    </div>

                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- end col -->
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function () {
        // تهيئة الجداول
        let liveTable = $('#live-transactions').DataTable();
        let demoTable = null;

        // عند التبديل بين التبويبات
        $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            let target = $(e.target).attr("href"); // الحصول على معرف التبويب المفتوح

            if (target === '#demo' && !demoTable) {
                // تهيئة جدول Demo فقط عند فتح التبويب لأول مرة
                demoTable = $('#demo-transactions').DataTable();
            }
        });
    });
</script>
@endsection

