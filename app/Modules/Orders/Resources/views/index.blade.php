@extends('dashboard.layouts.app')

@section('title', $title)

@section('styles')
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
            word-break: break-word;
        }

        .badge-soft {
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 20px;
            display: inline-block;
        }

        .badge-open {
            background: #e7f1ff;
            color: #0d6efd;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-closed {
            background: #d1e7dd;
            color: #0f5132;
        }

        .badge-default {
            background: #f1f3f5;
            color: #495057;
        }

        .custom-pagination .pagination {
            margin: 0;
        }

        .custom-pagination .page-item .page-link {
            padding: 6px 12px;
            font-size: 13px;
        }

        .custom-pagination .page-item.active .page-link {
            background-color: #556ee6;
            border-color: #556ee6;
            color: #fff;
        }

        .custom-pagination .page-link {
            border-radius: 6px !important;
            margin: 0 2px;
        }

        /* Custom Modal */
        .custom-order-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
        }

        .custom-order-modal.show {
            display: block;
        }

        .custom-order-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
        }

        .custom-order-modal-dialog {
            position: relative;
            width: min(1100px, calc(100% - 30px));
            margin: 30px auto;
            max-height: calc(100vh - 60px);
            overflow: hidden;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,.2);
        }

        .custom-order-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            border-bottom: 1px solid #e9ecef;
        }

        .custom-order-modal-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .custom-order-modal-close {
            border: 0;
            background: transparent;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
            color: #6c757d;
        }

        .custom-order-modal-body {
            padding: 18px;
            overflow-y: auto;
            max-height: calc(100vh - 140px);
        }
    </style>
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
                    <div class="table-responsive pt-0">
                        <table id="example" class="table table-bordered dt-responsive nowrap"
                               style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>{{ transWord('User') }}</th>
                                    <th>{{ transWord('Product') }}</th>
                                    <th>{{ transWord('Type') }}</th>
                                    <th>{{ transWord('Status') }}</th>
                                    <th>{{ transWord('Metal') }}</th>
                                    <th>{{ transWord('Currency') }}</th>
                                    <th>{{ transWord('Quantity') }}</th>
                                    <th>{{ transWord('Price') }}</th>
                                    <th>{{ transWord('Created At') }}</th>
                                    <th>{{ transWord('Actions') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($orders as $index => $order)
                                    <tr class="row_{{ $order->id }}">
                                        <td>{{ $orders->firstItem() + $index }}</td>
                                        <td>{{ optional($order->user)->name ?: '-' }}</td>
                                        <td>
                                            {{ optional($order->product)->name
                                                ?? optional($order->product)->title
                                                ?? optional($order->product)->product_name
                                                ?? '-' }}
                                        </td>
                                        <td>{{ $order->type ?: '-' }}</td>
                                        <td>
                                            @php
                                                $status = strtolower((string) $order->status);
                                                $statusClass = 'badge-default';

                                                if ($status === 'open') {
                                                    $statusClass = 'badge-open';
                                                } elseif ($status === 'pending') {
                                                    $statusClass = 'badge-pending';
                                                } elseif ($status === 'closed') {
                                                    $statusClass = 'badge-closed';
                                                }
                                            @endphp

                                            <span class="badge-soft {{ $statusClass }}">
                                                {{ $order->status ?: '-' }}
                                            </span>
                                        </td>
                                        <td>{{ $order->metal ?: '-' }}</td>
                                        <td>{{ $order->currency ?: '-' }}</td>
                                        <td>{{ $order->quantity ?? $order->qty ?? '-' }}</td>
                                        <td>{{ $order->price ?? '-' }}</td>
                                        <td>{{ $order->created_at }}</td>
                                        <td>
                                            <button type="button"
                                                    class="btn btn-info btn-sm"
                                                    onclick="openOrderModal('orderDetailsModal{{ $order->id }}')">
                                                {{ transWord('View Details') }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @foreach($orders as $order)
                        <div class="custom-order-modal" id="orderDetailsModal{{ $order->id }}">
                            <div class="custom-order-modal-backdrop" onclick="closeOrderModal('orderDetailsModal{{ $order->id }}')"></div>

                            <div class="custom-order-modal-dialog">
                                <div class="custom-order-modal-header">
                                    <h5 class="custom-order-modal-title">
                                        {{ transWord('Order Details') }} - #{{ $order->id }}
                                    </h5>
                                    <button type="button"
                                            class="custom-order-modal-close"
                                            onclick="closeOrderModal('orderDetailsModal{{ $order->id }}')">
                                        &times;
                                    </button>
                                </div>

                                <div class="custom-order-modal-body">
                                    <div class="row g-3">

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('User') }}</span>
                                            <div class="details-value">{{ optional($order->user)->name ?: '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Product') }}</span>
                                            <div class="details-value">
                                                {{ optional($order->product)->name
                                                    ?? optional($order->product)->title
                                                    ?? optional($order->product)->product_name
                                                    ?? '-' }}
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Status') }}</span>
                                            <div class="details-value">{{ $order->status ?: '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Type') }}</span>
                                            <div class="details-value">{{ $order->type ?: '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Mode') }}</span>
                                            <div class="details-value">{{ $order->mode ?: '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Metal') }}</span>
                                            <div class="details-value">{{ $order->metal ?: '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Currency') }}</span>
                                            <div class="details-value">{{ $order->currency ?: '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Quantity') }}</span>
                                            <div class="details-value">{{ $order->quantity ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Qty (grams)') }}</span>
                                            <div class="details-value">{{ $order->qty ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Unit Gram Weight') }}</span>
                                            <div class="details-value">{{ $order->unit_gram_weight ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Unit Karat') }}</span>
                                            <div class="details-value">{{ $order->unit_karat ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Price') }}</span>
                                            <div class="details-value">{{ $order->price ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Entry Price') }}</span>
                                            <div class="details-value">{{ $order->entry_price ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Executed Price') }}</span>
                                            <div class="details-value">{{ $order->executed_price ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Close Price') }}</span>
                                            <div class="details-value">{{ $order->close_price ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Open Price') }}</span>
                                            <div class="details-value">{{ $order->open_price ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Prev Close Price') }}</span>
                                            <div class="details-value">{{ $order->prev_close_price ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Low Price') }}</span>
                                            <div class="details-value">{{ $order->low_price ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('High Price') }}</span>
                                            <div class="details-value">{{ $order->high_price ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Ask') }}</span>
                                            <div class="details-value">{{ $order->ask ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Bid') }}</span>
                                            <div class="details-value">{{ $order->bid ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('CH') }}</span>
                                            <div class="details-value">{{ $order->ch ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('CHP') }}</span>
                                            <div class="details-value">{{ $order->chp ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Stop Loss') }}</span>
                                            <div class="details-value">{{ $order->stop_loss ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Sell When Price') }}</span>
                                            <div class="details-value">{{ $order->sell_when_price ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Take Profit') }}</span>
                                            <div class="details-value">{{ $order->take_profit ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Realized PNL') }}</span>
                                            <div class="details-value">{{ $order->realized_pnl ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Unrealized PNL') }}</span>
                                            <div class="details-value">{{ $order->unrealized_pnl ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Close Kind') }}</span>
                                            <div class="details-value">{{ $order->close_kind ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Open Time') }}</span>
                                            <div class="details-value">{{ $order->open_time ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Closed At') }}</span>
                                            <div class="details-value">{{ $order->closed_at ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Created At') }}</span>
                                            <div class="details-value">{{ $order->created_at ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Updated At') }}</span>
                                            <div class="details-value">{{ $order->updated_at ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-12"><hr></div>

                                        <div class="col-md-3">
                                            <span class="details-label">{{ transWord('24K Gram Price') }}</span>
                                            <div class="details-value">{{ $order->price_gram_24k ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-3">
                                            <span class="details-label">{{ transWord('22K Gram Price') }}</span>
                                            <div class="details-value">{{ $order->price_gram_22k ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-3">
                                            <span class="details-label">{{ transWord('21K Gram Price') }}</span>
                                            <div class="details-value">{{ $order->price_gram_21k ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-3">
                                            <span class="details-label">{{ transWord('20K Gram Price') }}</span>
                                            <div class="details-value">{{ $order->price_gram_20k ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('18K Gram Price') }}</span>
                                            <div class="details-value">{{ $order->price_gram_18k ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('16K Gram Price') }}</span>
                                            <div class="details-value">{{ $order->price_gram_16k ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('14K Gram Price') }}</span>
                                            <div class="details-value">{{ $order->price_gram_14k ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('10K Gram Price') }}</span>
                                            <div class="details-value">{{ $order->price_gram_10k ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-12"><hr></div>

                                        <div class="col-md-6">
                                            <span class="details-label">{{ transWord('Address') }}</span>
                                            <div class="details-value">{{ $order->address ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-3">
                                            <span class="details-label">{{ transWord('Delivery Status') }}</span>
                                            <div class="details-value">{{ $order->delivery_status ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-3">
                                            <span class="details-label">{{ transWord('Delivery Method') }}</span>
                                            <div class="details-value">{{ $order->delivery_method ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Delivery City') }}</span>
                                            <div class="details-value">{{ $order->delivery_city ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Delivery Phone') }}</span>
                                            <div class="details-value">{{ $order->delivery_phone ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-4">
                                            <span class="details-label">{{ transWord('Delivery Fee') }}</span>
                                            <div class="details-value">{{ $order->delivery_fee ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-6">
                                            <span class="details-label">{{ transWord('Tracking No') }}</span>
                                            <div class="details-value">{{ $order->delivery_tracking_no ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-6">
                                            <span class="details-label">{{ transWord('Delivery Requested At') }}</span>
                                            <div class="details-value">{{ $order->delivery_requested_at ?? '-' }}</div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="text-muted small">
                            {{ $orders->firstItem() }} - {{ $orders->lastItem() }}
                            {{ transWord('of') }}
                            {{ $orders->total() }}
                        </div>

                        <div class="custom-pagination">
                            {{ $orders->onEachSide(1)->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    $(document).ready(function () {
        if ($.fn.DataTable.isDataTable('#example')) {
            $('#example').DataTable().destroy();
        }

        $('#example').DataTable({
            paging: false,
            info: false,
            searching: true,
            ordering: true,
            responsive: true,
            autoWidth: false,
            retrieve: true
        });
    });

    function openOrderModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeOrderModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;

        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.custom-order-modal.show').forEach(function(modal) {
                modal.classList.remove('show');
            });
            document.body.style.overflow = '';
        }
    });
</script>
@endsection