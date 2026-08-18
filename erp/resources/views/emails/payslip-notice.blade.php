<div style="font-family: sans-serif; line-height: 1.6; color: #333;">
    <h2>প্রিয় {{ $user->name }},</h2>
    <p>আপনার {{ $empSalary->month }}/{{ $empSalary->year }} মাসের বেতন বিবরণী এখন প্রস্তুত।</p>
    <p>নিচের লিঙ্কে ক্লিক করে আপনি আপনার পে-স্লিপটি দেখতে এবং ডাউনলোড করতে পারবেন:</p>
    <div style="text-align: center; margin: 30px 0; font-family: sans-serif;">
        <a href="{{ $viewUrl }}"
           style="background-color: #000000;
                  color: #ffffff;
                  display: inline-block;
                  font-family: sans-serif;
                  font-size: 16px;
                  font-weight: bold;
                  line-height: 45px;
                  text-align: center;
                  text-decoration: none;
                  width: 200px;
                  -webkit-text-size-adjust: none;
                  border-radius: 5px;">
           পে-স্লিপ দেখুন
        </a>
    </div>
    <p>নিরাপত্তার স্বার্থে এই লিঙ্কটি আগামী ৩০ দিন পর্যন্ত কার্যকর থাকবে।</p>
    <hr style="border: none; border-top: 1px solid #eee;">
    <p style="font-size: 12px; color: #777;">এটি একটি সিস্টেম জেনারেটেড ইমেল, দয়া করে এখানে রিপ্লাই করবেন না।</p>
    <p>ধন্যবাদ,<br><strong>HR বিভাগ, Epal Group</strong></p>
</div>
