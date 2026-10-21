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
    <style>
        .details-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 4px;
            display: block;
        }

        .details-value {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 10px 12px;
            min-height: 44px;
        }

        .receipt-preview {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 4px;
            background: #fff;
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
                                            <th>{{ transWord('Request At') }}</th>
                                            <th>{{ transWord('Details') }}</th>
                                            {{-- <th>{{ transWord('Actions') }}</th> --}}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($liveTransactions as $index => $value)
                                            @if ($value->user != null)
                                                <tr class="row_{{ $value->id }}">
                                                    <td>{{ $index + 1 }}</td>
                                                    <td>
                                                        {{ $value->user->name }}
                                                    </td>
                                                    <td>{{ $value->amount }}</td>
                                                    <td>{{ $value->created_at }}</td>
                                                    <td>
                                                        <button type="button"
                                                                class="btn btn-info btn-sm"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#withdrawDetailsModal{{ $value->id }}">
                                                            {{ transWord('View Details') }}
                                                        </button>

                                                        @if ($value->is_approval == 0)
                                                            <a onclick="return confirm('Are You Sure?')"
                                                                href="{{ route('approve_withdraw', ['id' => $value->id]) }}"
                                                                class="btn btn-primary btn-sm">
                                                                {{ transWord('Approve') }}
                                                            </a>
                                                        @endif
                                                    </td>
                                                    {{-- <td>
                                                        @if ($value->is_approval == 0)
                                                            <a onclick="return confirm('Are You Sure?')"
                                                                href="{{ route('approve_withdraw', ['id' => $value->id]) }}"
                                                                class="btn btn-primary btn-sm">{{ transWord('Approve') }}</a>
                                                        @endif
                                                    </td> --}}
                                                </tr>
                                                <div class="modal fade" id="withdrawDetailsModal{{ $value->id }}" tabindex="-1"
                                                    aria-labelledby="withdrawDetailsModalLabel{{ $value->id }}" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="withdrawDetailsModalLabel{{ $value->id }}">
                                                                    {{ transWord('Withdrawal Details') }} - #{{ $value->id }}
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>

                                                            <div class="modal-body">
                                                                <div class="row g-3">
                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('User') }}:</strong>
                                                                        <div>{{ optional($value->user)->name }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Amount') }}:</strong>
                                                                        <div>{{ $value->amount }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Currency') }}:</strong>
                                                                        <div>{{ $value->currency ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Payment Method') }}:</strong>
                                                                        <div>{{ $value->payment_method ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Account Name') }}:</strong>
                                                                        <div>{{ $value->account_name ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Account Phone') }}:</strong>
                                                                        <div>{{ $value->account_phone ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Bank Name') }}:</strong>
                                                                        <div>{{ $value->bank_name ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Bank Account') }}:</strong>
                                                                        <div>{{ $value->bank_account ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Address') }}:</strong>
                                                                        <div>{{ $value->address ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Binance ID') }}:</strong>
                                                                        <div>{{ $value->binanceId ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Transaction ID') }}:</strong>
                                                                        <div>{{ $value->transaction_id ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Approval Status') }}:</strong>
                                                                        <div>
                                                                            @if($value->is_approval == 1)
                                                                                <span class="badge bg-success">{{ transWord('Approved') }}</span>
                                                                            @else
                                                                                <span class="badge bg-warning text-dark">{{ transWord('Pending') }}</span>
                                                                            @endif
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Approved By') }}:</strong>
                                                                        <div>{{ optional($value->approvedBy)->name ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-12">
                                                                        <strong>{{ transWord('Note') }}:</strong>
                                                                        <div>{{ $value->note ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Created At') }}:</strong>
                                                                        <div>{{ $value->created_at }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Approved At') }}:</strong>
                                                                        <div>{{ $value->approve_at ?: '-' }}</div>
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
                                            <th>{{ transWord('Request At') }}</th>
                                            <th>{{ transWord('Details') }}</th>
                                            {{-- <th>{{ transWord('Actions') }}</th> --}}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($demoTransactions as $index => $value)
                                            @if ($value->user != null)
                                                <tr class="row_{{ $value->id }}">
                                                    <td>{{ $index + 1 }}</td>
                                                    <td>
                                                        {{ $value->user->name }}
                                                    </td>
                                                    <td>{{ $value->amount }}</td>
                                                    <td>{{ $value->created_at }}</td>
                                                    <td>
                                                        <button type="button"
                                                                class="btn btn-info btn-sm"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#withdrawDetailsModal{{ $value->id }}">
                                                            {{ transWord('View Details') }}
                                                        </button>

                                                        @if ($value->is_approval == 0)
                                                            <a onclick="return confirm('Are You Sure?')"
                                                                href="{{ route('approve_withdraw', ['id' => $value->id]) }}"
                                                                class="btn btn-primary btn-sm">
                                                                {{ transWord('Approve') }}
                                                            </a>
                                                        @endif
                                                    </td>
                                                    {{-- <td>
                                                        @if ($value->is_approval == 0)
                                                            <a onclick="return confirm('Are You Sure?')"
                                                                href="{{ route('approve_withdraw', ['id' => $value->id]) }}"
                                                                class="btn btn-primary btn-sm">{{ transWord('Approve') }}</a>
                                                        @endif
                                                    </td> --}}
                                                </tr>
                                                <div class="modal fade" id="withdrawDetailsModal{{ $value->id }}" tabindex="-1"
                                                    aria-labelledby="withdrawDetailsModalLabel{{ $value->id }}" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="withdrawDetailsModalLabel{{ $value->id }}">
                                                                    {{ transWord('Withdrawal Details') }} - #{{ $value->id }}
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>

                                                            <div class="modal-body">
                                                                <div class="row g-3">
                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('User') }}:</strong>
                                                                        <div>{{ optional($value->user)->name }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Amount') }}:</strong>
                                                                        <div>{{ $value->amount }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Currency') }}:</strong>
                                                                        <div>{{ $value->currency ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Payment Method') }}:</strong>
                                                                        <div>{{ $value->payment_method ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Account Name') }}:</strong>
                                                                        <div>{{ $value->account_name ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Account Phone') }}:</strong>
                                                                        <div>{{ $value->account_phone ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Bank Name') }}:</strong>
                                                                        <div>{{ $value->bank_name ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Bank Account') }}:</strong>
                                                                        <div>{{ $value->bank_account ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Address') }}:</strong>
                                                                        <div>{{ $value->address ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Binance ID') }}:</strong>
                                                                        <div>{{ $value->binanceId ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Transaction ID') }}:</strong>
                                                                        <div>{{ $value->transaction_id ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Approval Status') }}:</strong>
                                                                        <div>
                                                                            @if($value->is_approval == 1)
                                                                                <span class="badge bg-success">{{ transWord('Approved') }}</span>
                                                                            @else
                                                                                <span class="badge bg-warning text-dark">{{ transWord('Pending') }}</span>
                                                                            @endif
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Approved By') }}:</strong>
                                                                        <div>{{ optional($value->approvedBy)->name ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-12">
                                                                        <strong>{{ transWord('Note') }}:</strong>
                                                                        <div>{{ $value->note ?: '-' }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Created At') }}:</strong>
                                                                        <div>{{ $value->created_at }}</div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <strong>{{ transWord('Approved At') }}:</strong>
                                                                        <div>{{ $value->approve_at ?: '-' }}</div>
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
