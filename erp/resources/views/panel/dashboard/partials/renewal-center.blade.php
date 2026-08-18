        <div class="rn-panel" data-active-source="{{ $dmSources[0]['key'] }}">
            <div class="rn-panel-h">
                <span class="rn-panel-title">
                    <span class="rn-bell-ic">🔔</span>
                    Renewal Center
                </span>
                <div class="rn-panel-actions">
                    <span class="rn-panel-badge rn-panel-badge-overdue rn-overdue-badge {{ $dmSources[0]['overdue'] > 0 ? '' : 'is-zero' }}"
                        title="Already past their due date">{{ $dmSources[0]['overdue'] }} Overdue</span>
                    <span class="rn-panel-badge rn-count-badge">{{ $dmSources[0]['count'] }} Due</span>
                    <div class="rn-source-nav" aria-label="Renewal source navigation">
                        <button type="button" class="rn-source-btn" data-nav="prev" title="Previous source">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="rn-source-label">{{ $dmSources[0]['label'] }}</span>
                        <button type="button" class="rn-source-btn" data-nav="next" title="Next source">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="rn-panel-sub">{{ $dmSources[0]['note'] }}</div>

            <div class="rn-source-pane active" data-source="all">
                <div class="rn-items-scroll-wrap">
                    <div class="rn-items">
                        @php $dmOverdueDividerDone = false; @endphp
                        @forelse($dmAllCollection as $item)
                            @if(!$item['is_overdue'] && !$dmOverdueDividerDone && $dmOverdueCount > 0)
                                @php $dmOverdueDividerDone = true; @endphp
                                <div class="rn-group-divider">Upcoming this month</div>
                            @endif

                            <div class="rn-item rn-{{ $item['priority'] }} rn-item-open"
                                @if($item['ref']) data-headline-ref="{{ $item['ref'] }}" @endif
                                data-type="{{ $item['type'] }}"
                                data-priority="{{ $item['priority'] }}"
                                data-title="{{ $item['title'] }}"
                                data-company="{{ $item['company'] }}"
                                data-access-type="{{ $item['access_type'] }}"
                                data-subscription-type="{{ $item['subscription_type'] }}"
                                data-currency="{{ $item['currency'] }}"
                                data-amount="{{ $item['amount'] ?? '—' }}"
                                data-due-date="{{ $item['due_text'] }}"
                                data-days-label="{{ $item['days_label'] }}"
                                data-renewal-date="{{ $item['renewal_text'] }}"
                                data-expired-date="{{ $item['expired_text'] }}"
                                data-email="{{ $item['email'] }}"
                                data-phone="{{ $item['phone'] }}"
                                data-notes="{{ $item['notes'] }}"
                                data-link-url="{{ $item['link_url'] }}"
                                data-priority-label="{{ $item['priority_label'] }}"
                                role="button"
                                tabindex="0"
                                title="Click to view {{ strtolower($item['source_label']) }} details">
                                <div class="rn-item-left">
                                    <div class="rn-item-icon {{ $item['icon_class'] }}">{{ $item['icon'] }}</div>
                                    <div class="rn-item-info">
                                        <div class="rn-item-name">
                                            {{ $item['title'] }}
                                            @if($item['company'] && $item['company'] !== 'N/A')
                                                · {{ $item['company'] }}
                                            @endif
                                        </div>
                                        <div class="rn-item-meta">
                                            <span class="rn-src-tag rn-src-{{ $item['type'] }}">{{ $item['source_label'] }}</span>
                                            {{ $item['access_type'] }} · {{ $item['subscription_type'] }}
                                            @if($item['amount'] !== null)
                                                · <strong>{{ $item['currency'] }} {{ $item['amount'] }}</strong>
                                            @endif
                                        </div>
                                        <div class="rn-item-meta">
                                            Due: <strong>{{ $item['due_text'] }}</strong>
                                            @if($item['is_overdue'])
                                                · <span class="rn-overdue-flag">Overdue</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="rn-item-right">
                                    <span class="rn-days-badge {{ $item['days_class'] }}">{{ $item['days_label'] }}</span>
                                    @if($item['link_url'] !== '#')
                                        <a href="{{ $item['link_url'] }}" target="_blank" rel="noopener noreferrer" class="rn-prio-btn rn-prio-{{ $item['priority'] }}" title="Open linked item">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rn-item rn-medium" data-priority="medium">
                                <div class="rn-item-left">
                                    <div class="rn-item-icon rn-ic-amber">ℹ️</div>
                                    <div class="rn-item-info">
                                        <div class="rn-item-name">Nothing due in {{ \Carbon\Carbon::today()->format('F Y') }}</div>
                                        <div class="rn-item-meta">No subscriptions or document renewals fall due this month</div>
                                    </div>
                                </div>
                            </div>
                        @endforelse
                    </div>
                    <div class="rn-scroll-btn-row">
                        <button type="button" class="rn-scroll-btn" data-dir="up" title="Scroll up">
                            <i class="fas fa-chevron-up"></i>
                        </button>
                        <button type="button" class="rn-scroll-btn" data-dir="down" title="Scroll down">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="rn-source-pane" data-source="subscriptions">
                <div class="rn-items-scroll-wrap">
                    <div class="rn-items">
                        @forelse($dmAccessCollection as $access)
                            @php
                                $companyName = data_get($access, 'company.name') ?: data_get($access, 'company_name') ?: 'N/A';
                                $dmTitle = data_get($access, 'name') ?: 'Untitled';
                                $accessType = data_get($access, 'access_type') ?: data_get($access, 'subscription_type') ?: 'Access';
                                $subscriptionType = data_get($access, 'subscription_type') ?: '—';
                                $currency = data_get($access, 'currency') ?: '';
                                $amountValue = data_get($access, 'amount');
                                $amount = is_numeric($amountValue) ? number_format((float) $amountValue, 2) : $amountValue;
                                $renewalRaw = data_get($access, 'expired_date') ?: data_get($access, 'renewal_date');
                                $renewalDate = $renewalRaw ? \Carbon\Carbon::parse($renewalRaw) : null;
                                $daysLeft = null;
                                if ($renewalDate) {
                                    $daysLeft = \Carbon\Carbon::today()->diffInDays($renewalDate, false);
                                }

                                if ($daysLeft !== null && $daysLeft < 0) {
                                    $priority = 'rn-critical';
                                    $daysClass = 'rn-days-critical';
                                    $daysLabel = 'Expired';
                                } elseif ($daysLeft !== null && $daysLeft <= 7) {
                                    $priority = 'rn-high';
                                    $daysClass = 'rn-days-high';
                                    $daysLabel = abs($daysLeft) . 'd';
                                } elseif ($daysLeft !== null && $daysLeft <= 30) {
                                    $priority = 'rn-medium';
                                    $daysClass = 'rn-days-medium';
                                    $daysLabel = abs($daysLeft) . 'd';
                                } else {
                                    $priority = 'rn-medium';
                                    $daysClass = 'rn-days-medium';
                                    $daysLabel = $daysLeft !== null ? abs($daysLeft) . 'd' : '—';
                                }

                                $dateText = $renewalDate ? $renewalDate->format('d M Y') : '—';
                                $renewalText = data_get($access, 'renewal_date') ? \Carbon\Carbon::parse(data_get($access, 'renewal_date'))->format('d M Y') : '—';
                                $expiredText = data_get($access, 'expired_date') ? \Carbon\Carbon::parse(data_get($access, 'expired_date'))->format('d M Y') : '—';
                                $notes = data_get($access, 'notes') ?: 'No notes';
                                $email = data_get($access, 'email') ?: data_get($access, 'username') ?: '—';
                                $phone = data_get($access, 'phone') ?: '—';
                                $linkUrl = data_get($access, 'url') ?: '#';
                            @endphp

                            <div class="rn-item {{ $priority }} rn-item-open"
                                data-type="subscription"
                                data-priority="{{ str_replace('rn-', '', $priority) }}"
                                data-title="{{ $dmTitle }}"
                                data-company="{{ $companyName }}"
                                data-access-type="{{ $accessType }}"
                                data-subscription-type="{{ $subscriptionType }}"
                                data-currency="{{ $currency }}"
                                data-amount="{{ $amount ?? '—' }}"
                                data-due-date="{{ $dateText }}"
                                data-days-label="{{ $daysLabel }}"
                                data-renewal-date="{{ $renewalText }}"
                                data-expired-date="{{ $expiredText }}"
                                data-email="{{ $email }}"
                                data-phone="{{ $phone }}"
                                data-notes="{{ $notes }}"
                                data-link-url="{{ $linkUrl }}"
                                data-priority-label="{{ ucfirst(str_replace('rn-', '', $priority)) }}"
                                role="button"
                                tabindex="0"
                                title="Click to view subscription details">
                                <div class="rn-item-left">
                                    <div class="rn-item-icon rn-ic-orange">🔔</div>
                                    <div class="rn-item-info">
                                        <div class="rn-item-name">{{ $dmTitle }} · {{ $companyName }}</div>
                                        <div class="rn-item-meta">
                                            {{ $accessType }} · {{ $subscriptionType }}
                                            @if($amount !== null)
                                                · <strong>{{ $currency }} {{ $amount }}</strong>
                                            @endif
                                        </div>
                                        <div class="rn-item-meta">
                                            Due: <strong>{{ $dateText }}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="rn-item-right">
                                    <span class="rn-days-badge {{ $daysClass }}">{{ $daysLabel }}</span>
                                    <a href="{{ $linkUrl }}" target="_blank" rel="noopener noreferrer" class="rn-prio-btn rn-prio-medium" title="Open access link">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="rn-item rn-medium" data-priority="medium">
                                <div class="rn-item-left">
                                    <div class="rn-item-icon rn-ic-amber">ℹ️</div>
                                    <div class="rn-item-info">
                                        <div class="rn-item-name">No subscription records found</div>
                                        <div class="rn-item-meta">DM API returned an empty list</div>
                                    </div>
                                </div>
                            </div>
                        @endforelse
                    </div>
                    <div class="rn-scroll-btn-row">
                        <button type="button" class="rn-scroll-btn" data-dir="up" title="Scroll up">
                            <i class="fas fa-chevron-up"></i>
                        </button>
                        <button type="button" class="rn-scroll-btn" data-dir="down" title="Scroll down">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="rn-source-pane" data-source="documents">
                <div class="rn-items-scroll-wrap">
                    <div class="rn-items">
                        {{-- Rows arrive upcoming-first with expiries last, so the
                             divider only needs to fire on the first lapsed row. --}}
                        @php $dmDocExpiredDividerDone = false; @endphp
                        @forelse($dmDocumentCollection as $document)
                            @php
                                $documentTitle = data_get($document, 'documents.title') ?: data_get($document, 'title') ?: 'Untitled';
                                $documentType = data_get($document, 'document_type.name') ?: ('Type #' . (data_get($document, 'document_type_id') ?: '—'));
                                $documentCategory = data_get($document, 'document_category.name') ?: ('Category #' . (data_get($document, 'document_category_id') ?: '—'));
                                $expiryRaw = data_get($document, 'renewal_date') ?: data_get($document, 'expired_date') ?: data_get($document, 'documents.renewal_date');
                                $createdRaw = data_get($document, 'created_date') ?: data_get($document, 'documents.created_date');
                                $expiryDate = $expiryRaw ? \Carbon\Carbon::parse($expiryRaw) : null;
                                $daysLeft = $expiryDate ? \Carbon\Carbon::today()->diffInDays($expiryDate, false) : null;

                                if ($daysLeft !== null && $daysLeft < 0) {
                                    $priority = 'rn-critical';
                                    $daysClass = 'rn-days-critical';
                                    $daysLabel = 'Expired';
                                } elseif ($daysLeft !== null && $daysLeft <= 7) {
                                    $priority = 'rn-high';
                                    $daysClass = 'rn-days-high';
                                    $daysLabel = abs($daysLeft) . 'd';
                                } elseif ($daysLeft !== null && $daysLeft <= 30) {
                                    $priority = 'rn-medium';
                                    $daysClass = 'rn-days-medium';
                                    $daysLabel = abs($daysLeft) . 'd';
                                } else {
                                    $priority = 'rn-medium';
                                    $daysClass = 'rn-days-medium';
                                    $daysLabel = $daysLeft !== null ? abs($daysLeft) . 'd' : '—';
                                }

                                $expiryText = $expiryDate ? $expiryDate->format('d M Y') : '—';
                                $createdText = $createdRaw ? \Carbon\Carbon::parse($createdRaw)->format('d M Y') : '—';
                                $documentSummary = sprintf('%s · %s · Created %s', $documentType, $documentCategory, $createdText);
                                $fileUrl = data_get($document, 'file_path') ?: data_get($document, 'image_path') ?: '#';
                                $isExpiredDoc = $daysLeft !== null && $daysLeft < 0;
                            @endphp

                            @if($isExpiredDoc && !$dmDocExpiredDividerDone)
                                @php $dmDocExpiredDividerDone = true; @endphp
                                <div class="rn-group-divider">Expired</div>
                            @endif

                            <div class="rn-item {{ $priority }} rn-item-open"
                                data-type="document"
                                data-priority="{{ str_replace('rn-', '', $priority) }}"
                                data-title="{{ $documentTitle }}"
                                data-company="Document Renewal"
                                data-access-type="{{ $documentType }}"
                                data-subscription-type="{{ $documentCategory }}"
                                data-currency=""
                                data-amount="—"
                                data-due-date="{{ $expiryText }}"
                                data-days-label="{{ $daysLabel }}"
                                data-renewal-date="{{ $createdText }}"
                                data-expired-date="{{ $expiryText }}"
                                data-email="—"
                                data-phone="—"
                                data-notes="{{ $documentSummary }}"
                                data-link-url="{{ $fileUrl }}"
                                data-priority-label="{{ ucfirst(str_replace('rn-', '', $priority)) }}"
                                role="button"
                                tabindex="0"
                                title="Click to view document renewal details">
                                <div class="rn-item-left">
                                    <div class="rn-item-icon rn-ic-blue">📄</div>
                                    <div class="rn-item-info">
                                        <div class="rn-item-name">{{ $documentTitle }}</div>
                                        <div class="rn-doc-grid" style="margin-top:6px;">
                                            <div class="rn-doc-cell">
                                                <span class="rn-doc-label">Title</span>
                                                <span class="rn-doc-value">{{ $documentTitle }}</span>
                                            </div>
                                            <div class="rn-doc-cell">
                                                <span class="rn-doc-label">Document Type</span>
                                                <span class="rn-doc-value">{{ $documentType }}</span>
                                            </div>
                                            <div class="rn-doc-cell">
                                                <span class="rn-doc-label">Document Category</span>
                                                <span class="rn-doc-value">{{ $documentCategory }}</span>
                                            </div>
                                            <div class="rn-doc-cell">
                                                <span class="rn-doc-label">Expiry Date</span>
                                                <span class="rn-doc-value">{{ $expiryText }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="rn-item-right">
                                    <span class="rn-days-badge {{ $daysClass }}">{{ $daysLabel }}</span>
                                    @if($fileUrl !== '#')
                                        <a href="{{ $fileUrl }}" target="_blank" rel="noopener noreferrer" class="rn-prio-btn rn-prio-medium" title="Open document file">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rn-item rn-medium" data-priority="medium">
                                <div class="rn-item-left">
                                    <div class="rn-item-icon rn-ic-amber">ℹ️</div>
                                    <div class="rn-item-info">
                                        <div class="rn-item-name">No document renewals found</div>
                                        <div class="rn-item-meta">DM API returned an empty list</div>
                                    </div>
                                </div>
                            </div>
                        @endforelse
                    </div>
                    <div class="rn-scroll-btn-row">
                        <button type="button" class="rn-scroll-btn" data-dir="up" title="Scroll up">
                            <i class="fas fa-chevron-up"></i>
                        </button>
                        <button type="button" class="rn-scroll-btn" data-dir="down" title="Scroll down">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="rn-view-more-row">
                <span class="rn-total-note">{{ $dmSources[0]['count'] }} total · live sync</span>
            </div>
        </div>
