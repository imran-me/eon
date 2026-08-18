<div id="viewModal" class="fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop">
 <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl" onclick="event.stopPropagation()">
  <header class="flex items-center gap-3 bg-gradient-to-r from-slate-700 to-slate-600 px-5 py-4 text-white"><span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white/20"><i class="fas fa-eye"></i></span><div><h2 class="text-sm font-bold">File Details <span id="view_file_number"></span></h2><p class="text-[11px] text-slate-300">Applicant, vendor and document overview</p></div><button class="modal-close-view ml-auto flex h-8 w-8 items-center justify-center rounded-full bg-white/20"><i class="fas fa-times text-xs"></i></button></header>
  <div class="overflow-y-auto p-5"><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
   @foreach([['Applicant','view_applicant'],['Passport','view_passport'],['Phone','view_phone'],['Nationality','view_nationality'],['Country','view_country'],['Category','view_category'],['Vendor','view_vendor'],['Visa Rate','view_visa_rate'],['Submit Date','view_submit_date'],['Expected Out','view_expected_out_date'],['Status','view_status'],['Notes','view_notes']] as [$label,$id])
   <div class="rounded-lg bg-slate-50 p-3"><span class="block text-[10px] font-bold uppercase text-slate-500">{{ $label }}</span><span id="{{ $id }}" class="mt-1 block text-sm font-semibold text-slate-800"></span></div>
   @endforeach
  </div><div class="mt-4 border-t pt-4"><h3 class="mb-3 text-xs font-bold uppercase text-slate-600"><i class="fas fa-file-lines mr-1"></i>Documents</h3><div id="view_documents"></div></div></div>
  <footer class="flex justify-end bg-slate-50 px-5 py-3"><button class="modal-close-view rounded-lg border px-4 py-2 text-xs font-semibold">Close</button></footer>
 </div>
</div>
