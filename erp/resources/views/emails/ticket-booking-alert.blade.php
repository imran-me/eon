<h2>Ticket booking {{ $eventLabel }}</h2>
<p><strong>Reference:</strong> {{ $booking->booking_reference }}</p>
<p><strong>PNR:</strong> {{ $booking->pnr ?: 'Pending' }}</p>
<p><strong>Passenger:</strong> {{ $booking->passenger?->name }}</p>
<p><strong>Route:</strong> {{ $booking->ticket?->title }}</p>
<p><strong>Expires:</strong> {{ $booking->expires_at->format('d M Y, h:i A') }}</p>
<p><strong>Quoted amount:</strong> {{ $booking->currency }} {{ number_format((float) $booking->quoted_amount, 2) }}</p>
<p><a href="{{ $bookingUrl }}">Review booking in Epal ERP</a></p>
