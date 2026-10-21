@extends('dashboard.layouts.app')

@section('title', $title)

@section('styles')
<style>
    .modal-dialog-scrollable { height: auto !important; }
    .value-preview { white-space: pre-wrap; }
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
  <!-- Add/Edit Form (واحد) -->
  <div class="col-12">
    <div class="card mb-3">
      <div class="card-header bg-white d-flex align-items-center justify-content-between border-bottom">
        <strong class="card-title" id="pmFormTitle">{{ transWord('Add Payment Method') }}</strong>
      </div>
      <div class="card-body">
        <form id="pmForm" method="POST" action="{{ route('payment_methods.store') }}">
          @csrf
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">{{ transWord('Name') }}</label>
              <input type="text" id="pm_name" name="name" class="form-control" required
                     placeholder="{{ transWord('e.g. Binance / NBE Bank Account') }}"
                     value="{{ old('name') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ transWord('Value (Text)') }}</label>
              <textarea id="pm_value" name="value" rows="3" class="form-control" required
                        placeholder="{{ transWord('Put the deposit address / IBAN / account number / notes ...') }}">{{ old('value') }}</textarea>
              <small class="text-muted">{{ transWord('This will appear to users as plain text') }}</small>
            </div>
            <div class="col-md-2">
              <label class="form-label">{{ transWord('Sort Order') }}</label>
              <input type="number" id="pm_sort" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', 0) }}">
              <div class="form-check mt-2">
                {{-- دايمًا يبعت 0 لو اتشطّب --}}
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" id="pm_active" value="1"
                       {{ old('is_active', 1) ? 'checked' : '' }}>
                <label class="form-check-label" for="pm_active">{{ transWord('Active') }}</label>
              </div>
            </div>
          </div>

          <div class="mt-3 d-flex gap-2">
            <button type="submit" id="pmSubmitBtn" class="btn btn-primary">
              {{ transWord('Submit') }}
            </button>
            <button type="button" id="pmCancelEdit" class="btn btn-secondary d-none">
              {{ transWord('Cancel Edit') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="col-12">
    <div class="card">
      <div class="card-header bg-white d-flex align-items-center justify-content-between border-bottom">
        <strong class="card-title">{{ $title }}</strong>
      </div>
      <div class="card-body">
        <div class="table-responsive pt-0">
          <table id="paymentMethodsTbl" class="table table-bordered dt-responsive nowrap" style="border-collapse:collapse;border-spacing:0;width:100%;">
            <thead class="thead-light">
              <tr>
                <th>#</th>
                <th>{{ transWord('Name') }}</th>
                <th>{{ transWord('Value') }}</th>
                <th>{{ transWord('Active') }}</th>
                <th>{{ transWord('Sort') }}</th>
                <th>{{ transWord('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($methods as $i => $m)
                <tr class="row_{{ $m->id }}">
                  <td>{{ $i + 1 }}</td>
                  <td>{{ $m->name }}</td>
                  <td class="value-preview">{{ $m->value }}</td>
                  <td>
                    <span class="badge {{ $m->is_active ? 'bg-success' : 'bg-secondary' }}">
                      {{ $m->is_active ? transWord('Active') : transWord('Inactive') }}
                    </span>
                  </td>
                  <td>{{ $m->sort_order }}</td>
                  <td class="text-nowrap">
                    <!-- Edit (يملأ الفورم فوق) -->
                    <button
                      type="button"
                      class="btn btn-sm btn-warning btn-edit-pm"
                      data-id="{{ $m->id }}"
                      data-name="{{ $m->name }}"
                      data-value="{{ htmlspecialchars($m->value, ENT_QUOTES) }}"
                      data-sort="{{ (int)$m->sort_order }}"
                      data-active="{{ $m->is_active ? 1 : 0 }}"
                    >
                      {{ transWord('Edit') }}
                    </button>

                    <!-- Toggle -->
                    <form class="d-inline" method="POST" action="{{ route('payment_methods.toggle', $m->id) }}">
                      @csrf
                      <button class="btn btn-sm {{ $m->is_active ? 'btn-secondary' : 'btn-success' }}">
                        {{ $m->is_active ? transWord('Disable') : transWord('Activate') }}
                      </button>
                    </form>

                    <!-- Delete -->
                    <form class="d-inline" method="POST" action="{{ route('payment_methods.destroy', $m->id) }}"
                          onsubmit="return confirm('{{ transWord('Are you sure?') }}')">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm btn-danger">{{ transWord('Delete') }}</button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center text-muted">{{ transWord('No data found') }}</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
  // عناصر الفورم
  const form         = document.getElementById('pmForm');
  const titleEl      = document.getElementById('pmFormTitle');
  const nameEl       = document.getElementById('pm_name');
  const valueEl      = document.getElementById('pm_value');
  const sortEl       = document.getElementById('pm_sort');
  const activeEl     = document.getElementById('pm_active');
  const submitBtn    = document.getElementById('pmSubmitBtn');
  const cancelBtn    = document.getElementById('pmCancelEdit');

  // الروتس
  const createAction = @json(route('payment_methods.store'));
  const updateActionTemplate = @json(route('payment_methods.update', ['id' => '__ID__']));

  // إدخال وضع التعديل
  function enterEditMode(rowData) {
    // عدّل العنوان والنص
    titleEl.textContent = "{{ transWord('Edit Payment Method') }}";
    submitBtn.textContent = "{{ transWord('Save Changes') }}";
    cancelBtn.classList.remove('d-none');

    // عبّي القيم
    nameEl.value   = rowData.name || '';
    valueEl.value  = rowData.value || '';
    sortEl.value   = rowData.sort || 0;
    activeEl.checked = (String(rowData.active) === '1');

    // بدّل الـ action للرَوت بتاع التعديل
    form.setAttribute('action', updateActionTemplate.replace('__ID__', rowData.id));
  }

  // الرجوع لوضع الإضافة
  function exitEditMode() {
    titleEl.textContent = "{{ transWord('Add Payment Method') }}";
    submitBtn.textContent = "{{ transWord('Submit') }}";
    cancelBtn.classList.add('d-none');

    // فضّي القيم (أو رجع old() لو حابب)
    nameEl.value  = '';
    valueEl.value = '';
    sortEl.value  = 0;
    activeEl.checked = true;

    // رجّع الأكشن إلى الإضافة
    form.setAttribute('action', createAction);
  }

  // حدث زرار Edit في الجدول
  document.querySelectorAll('.btn-edit-pm').forEach(function(btn) {
    btn.addEventListener('click', function () {
      const rowData = {
        id:     this.getAttribute('data-id'),
        name:   this.getAttribute('data-name') || '',
        value:  this.getAttribute('data-value') || '',
        sort:   this.getAttribute('data-sort') || 0,
        active: this.getAttribute('data-active') || 0,
      };
      enterEditMode(rowData);
      // Scroll للفورم عشان يظهر فوق
      window.scrollTo({ top: form.offsetTop - 120, behavior: 'smooth' });
    });
  });

  // زرار إلغاء التعديل
  cancelBtn.addEventListener('click', function () {
    exitEditMode();
  });
})();
</script>
@endsection
