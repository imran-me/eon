<div style="font-family: sans-serif; line-height: 1.6; color: #333;">
    <h2>প্রিয় {{ $user->name }},</h2>
    @if($isPartial)
        <p>আপনার {{ $salary->month }}/{{ $salary->year }} মাসের বেতন থেকে ৳{{ number_format($paidAmt, 2) }} আংশিক পরিশোধ করা হয়েছে।</p>
        <p>বাকি বকেয়া পরিমাণ: <strong>৳{{ number_format($remainingDue, 2) }}</strong></p>
    @else
        <p>আপনার {{ $salary->month }}/{{ $salary->year }} মাসের বেতন <strong>৳{{ number_format($paidAmt, 2) }}</strong> সম্পূর্ণরূপে পরিশোধ করা হয়েছে।</p>
    @endif
    <p>পেমেন্ট মাধ্যম: {{ \Illuminate\Support\Str::headline($paymentMethod) }}</p>
    <p>পেমেন্ট তারিখ: {{ \Carbon\Carbon::parse($paymentDate)->format('d M, Y') }}</p>
    <hr style="border: none; border-top: 1px solid #eee;">
    <p style="font-size: 12px; color: #777;">এটি একটি সিস্টেম জেনারেটেড ইমেল, দয়া করে এখানে রিপ্লাই করবেন না।</p>
    <p>ধন্যবাদ,<br><strong>HR বিভাগ, Epal Group</strong></p>
</div>
