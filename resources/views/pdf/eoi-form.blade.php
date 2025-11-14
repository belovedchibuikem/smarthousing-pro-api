<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Expression of Interest Form</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            background-color: #ffffff;
            color: #111827;
            font-size: 10pt;
            line-height: 1.4;
        }
        .container {
            max-width: 100%;
            margin: 0;
            background-color: #ffffff;
        }
        
        /* Header Styles */
        header {
            padding: 20px;
            text-align: center;
            background-color: #f9fafb;
            border-bottom: 2px solid {{ $whiteLabel->primary_color ?? '#D97706' }};
        }
        header .logo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #e5e7eb;
            display: inline-block;
            text-align: center;
            line-height: 80px;
            margin-bottom: 10px;
        }
        header .logo img {
            max-width: 150px;
            max-height: 150px;
            vertical-align: middle;
        }
        header h1 {
            font-size: 16pt;
            font-weight: bold;
            letter-spacing: 1px;
            color: {{ $whiteLabel->secondary_color ?? '#1F2937' }};
            margin: 8px 0;
            text-transform: uppercase;
        }
        header p {
            margin: 4px 0;
            color: #6b7280;
            font-size: 9pt;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        header .project-title {
            margin-top: 8px;
            font-weight: bold;
            text-transform: uppercase;
            color: {{ $whiteLabel->primary_color ?? '#D97706' }};
            letter-spacing: 1px;
            font-size: 10pt;
        }
        
        /* Section Styles */
        .section {
            padding: 15px 20px;
            border-top: 1px solid #f3f4f6;
            page-break-inside: avoid;
        }
        .section:first-of-type {
            border-top: none;
        }
        .section h2 {
            font-size: 11pt;
            font-weight: bold;
            color: #1f2937;
            margin: 0 0 12px 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border-bottom: 2px solid {{ $whiteLabel->primary_color ?? '#D97706' }};
            padding-bottom: 6px;
        }
        
        /* Grid Layout */
        .grid {
            width: 100%;
        }
        .grid-item {
            display: inline-block;
            width: 48%;
            vertical-align: top;
            margin-bottom: 12px;
            margin-right: 2%;
        }
        .grid-item:nth-child(2n) {
            margin-right: 0;
        }
        .grid-item-full {
            display: block;
            width: 100%;
            margin-bottom: 12px;
        }
        .grid-item-third {
            display: inline-block;
            width: 31%;
            vertical-align: top;
            margin-bottom: 12px;
            margin-right: 2%;
        }
        .grid-item-third:nth-child(3n) {
            margin-right: 0;
        }
        
        /* Field Styles */
        .label {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 3px;
            font-weight: normal;
        }
        .value {
            font-size: 10pt;
            font-weight: bold;
            color: #111827;
            padding-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
            word-wrap: break-word;
            min-height: 20px;
        }
        
        /* Funding Note */
        .funding-note {
            margin-top: 12px;
            padding: 12px;
            background-color: #fffbeb;
            border: 1px dashed #fbbf24;
            border-left: 3px solid {{ $whiteLabel->primary_color ?? '#D97706' }};
            font-size: 9pt;
            color: #78350f;
            line-height: 1.5;
        }
        
        /* Signature Block */
        .signature-block {
            margin-top: 15px;
            width: 100%;
        }
        .signature-box {
            display: inline-block;
            width: 48%;
            border: 1px solid #d1d5db;
            padding: 12px;
            vertical-align: top;
            min-height: 100px;
            margin-right: 2%;
        }
        .signature-box:last-child {
            margin-right: 0;
        }
        .signature-box .title {
            font-size: 9pt;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .signature-image {
            margin-top: 8px;
            min-height: 70px;
            border: 1px dashed #d1d5db;
            background-color: #f9fafb;
            text-align: center;
            padding: 8px;
        }
        .signature-image img {
            max-width: 100%;
            max-height: 60px;
        }
        
        /* Mortgage Panel */
        .mortgage-panel {
            margin-top: 12px;
            border: 1px solid #fbbf24;
            background-color: #fffbeb;
            page-break-inside: avoid;
        }
        .mortgage-panel-header {
            padding: 10px 12px;
            background-color: #fef3c7;
            border-bottom: 1px solid #fbbf24;
        }
        .mortgage-panel-header h3 {
            margin: 0;
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #78350f;
            font-weight: bold;
        }
        .mortgage-panel-body {
            padding: 12px;
        }
        
        /* Certification Text */
        .certification-text {
            font-size: 10pt;
            line-height: 1.6;
            color: #111827;
            margin-bottom: 15px;
            padding: 12px;
            background-color: #f9fafb;
            border-left: 3px solid {{ $whiteLabel->primary_color ?? '#D97706' }};
        }
        .certification-text strong {
            font-weight: bold;
            color: {{ $whiteLabel->primary_color ?? '#D97706' }};
        }
        
        /* Footer */
        footer {
            padding: 15px 20px;
            text-align: center;
            font-size: 8pt;
            color: #9ca3af;
            border-top: 2px solid #e5e7eb;
            background-color: #f9fafb;
        }
        
        /* Table for better layout control */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 6px 8px;
            vertical-align: top;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header>
            <div class="logo">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo">
                @else
                    <span style="font-size: 14pt; font-weight: bold; color: {{ $whiteLabel->primary_color ?? '#D97706' }};">LOGO</span>
                @endif
            </div>
            <h1>{{ strtoupper($whiteLabel->company_name ?? 'FEDERAL ROAD SAFETY CORPS') }}</h1>
            <p>{{ strtoupper($whiteLabel->company_tagline ?? 'FRSC HOUSING COOPERATIVE SOCIETY') }}</p>
            <p class="project-title">APO WASA EXPRESSION OF INTEREST FORM</p>
            <p style="margin-top: 8px; font-size: 8pt;">{{ strtoupper($whiteLabel->contact_email ?? 'housing20000@frsc.gov.ng • frschousingcooperative@gmail.com') }}</p>
        </header>

        <!-- Personal Details -->
        <div class="section">
            <h2>Personal Details</h2>
            <div class="grid">
                <div class="grid-item">
                    <div class="label">Name of Applicant</div>
                    <div class="value">
                        {{ trim(($form->member->user->first_name ?? '') . ' ' . ($form->member->user->last_name ?? '')) ?: '—' }}
                    </div>
                </div>
                <div class="grid-item">
                    <div class="label">Rank</div>
                    <div class="value">{{ $form->member->rank ?? '—' }}</div>
                </div>
                <div class="grid-item">
                    <div class="label">PIN</div>
                    <div class="value">{{ $form->member->member_id ?? $form->member->staff_id ?? '—' }}</div>
                </div>
                <div class="grid-item">
                    <div class="label">IPPIS No</div>
                    <div class="value">{{ $form->member->ippis_number ?? '—' }}</div>
                </div>
                <div class="grid-item">
                    <div class="label">Command</div>
                    <div class="value">{{ $form->member->command ?? '—' }}</div>
                </div>
                <div class="grid-item">
                    <div class="label">Phone Number</div>
                    <div class="value">{{ $form->member->user->phone ?? '—' }}</div>
                </div>
                <div class="grid-item-full">
                    <div class="label">Email</div>
                    <div class="value">{{ $form->member->user->email ?? '—' }}</div>
                </div>
            </div>
        </div>

        <!-- Affordability Test -->
        <div class="section">
            <h2>Affordability Test</h2>
            <div class="grid">
                <div class="grid-item">
                    <div class="label">Net Salary (as at last pay slip)</div>
                    <div class="value">₦{{ number_format($form->net_salary ?? 0, 2) }}</div>
                </div>
                <div class="grid-item">
                    <div class="label">Currently on mortgage?</div>
                    <div class="value">{{ $form->has_existing_loan ? 'Yes' : 'No' }}</div>
                </div>
                <div class="grid-item-full">
                    <div class="label">Existing loan types</div>
                    <div class="value">
                        @php
                            $loanTypes = $form->existing_loan_types;
                            if (is_string($loanTypes)) {
                                $loanTypes = json_decode($loanTypes, true) ?? [];
                            }
                            $loanTypes = is_array($loanTypes) ? $loanTypes : [];
                            
                            if (!empty($loanTypes)) {
                                $typeNames = [];
                                foreach ($loanTypes as $loan) {
                                    if (is_array($loan) && isset($loan['type'])) {
                                        $typeNames[] = $loan['type'];
                                    } elseif (is_string($loan)) {
                                        $typeNames[] = $loan;
                                    }
                                }
                                echo !empty($typeNames) ? implode(', ', $typeNames) : '—';
                            } else {
                                echo '—';
                            }
                        @endphp
                    </div>
                </div>
            </div>
        </div>

        <!-- Next of Kin Details -->
        <div class="section">
            <h2>Next of Kin Details</h2>
            <div class="grid">
                <div class="grid-item">
                    <div class="label">Name</div>
                    <div class="value">{{ data_get($form->next_of_kin_snapshot, 'name', '—') }}</div>
                </div>
                <div class="grid-item">
                    <div class="label">Phone</div>
                    <div class="value">{{ data_get($form->next_of_kin_snapshot, 'phone', '—') }}</div>
                </div>
                <div class="grid-item">
                    <div class="label">Relationship</div>
                    <div class="value">{{ data_get($form->next_of_kin_snapshot, 'relationship', '—') }}</div>
                </div>
                <div class="grid-item">
                    <div class="label">Email</div>
                    <div class="value">{{ data_get($form->next_of_kin_snapshot, 'email', '—') }}</div>
                </div>
                <div class="grid-item-full">
                    <div class="label">Address</div>
                    <div class="value">{{ data_get($form->next_of_kin_snapshot, 'address', '—') }}</div>
                </div>
            </div>
        </div>

        <!-- Property Details -->
        <div class="section">
            <h2>Property Details</h2>
            <div class="grid">
                <div class="grid-item">
                    <div class="label">Property Name</div>
                    <div class="value">{{ $form->property_snapshot['title'] ?? $form->property->title ?? '—' }}</div>
                </div>
                <div class="grid-item">
                    <div class="label">Property Type</div>
                    <div class="value">{{ ucwords(str_replace('_', ' ', $form->property_snapshot['type'] ?? $form->property->type ?? '—')) }}</div>
                </div>
                <div class="grid-item">
                    <div class="label">Property Cost</div>
                    <div class="value">
                        @php
                            $price = $form->property_snapshot['price'] ?? $form->property->price ?? null;
                        @endphp
                        {{ $price ? '₦' . number_format($price, 2) : '—' }}
                    </div>
                </div>
                <div class="grid-item">
                    <div class="label">Location</div>
                    <div class="value">{{ $form->property_snapshot['location'] ?? $form->property->location ?? '—' }}</div>
                </div>
                <div class="grid-item-third">
                    <div class="label">Bedrooms</div>
                    <div class="value">{{ $form->property_snapshot['bedrooms'] ?? $form->property->bedrooms ?? '—' }}</div>
                </div>
                <div class="grid-item-third">
                    <div class="label">Bathrooms</div>
                    <div class="value">{{ $form->property_snapshot['bathrooms'] ?? $form->property->bathrooms ?? '—' }}</div>
                </div>
                <div class="grid-item-third">
                    <div class="label">Size</div>
                    <div class="value">
                        @php
                            $size = $form->property_snapshot['size'] ?? $form->property->size ?? null;
                        @endphp
                        {{ $size ? number_format($size) . ' sqm' : '—' }}
                    </div>
                </div>
                <div class="grid-item-full">
                    <div class="label">Features & Amenities</div>
                    @php
                        $features = $form->property_snapshot['features'] ?? $form->property->features ?? [];
                        if (is_string($features)) {
                            $features = json_decode($features, true) ?? [];
                        }
                        $features = is_array($features) ? array_filter($features) : [];
                    @endphp
                    <div class="value">
                        @if(!empty($features))
                            {{ implode(', ', $features) }}
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Funding -->
        <div class="section">
            <h2>Funding</h2>
            <div class="grid">
                <div class="grid-item">
                    <div class="label">Preferred Funding Option</div>
                    <div class="value">{{ strtoupper(str_replace('_', ' ', $form->funding_option ?? 'N/A')) }}</div>
                </div>
                <div class="grid-item">
                    <div class="label">Preferred Payment Methods</div>
                    <div class="value">
                        @php
                            $methods = $form->preferred_payment_methods;
                            if (is_string($methods)) {
                                $methods = json_decode($methods, true) ?? [];
                            }
                            $methods = is_array($methods) ? $methods : [];
                        @endphp
                        @if(!empty($methods))
                            {{ implode(', ', array_map(fn($method) => strtoupper(str_replace('_', ' ', $method)), $methods)) }}
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>
            <div class="funding-note">
                <strong>Note:</strong> For mix funding, equity is 20% while loan is 80%. Equity wallet deposits can be used for property deposits and payments.
            </div>

            @if($form->funding_option === 'mortgage' && !empty($form->mortgage_preferences))
                <div class="mortgage-panel">
                    <div class="mortgage-panel-header">
                        <h3>Mortgage Terms</h3>
                    </div>
                    <div class="mortgage-panel-body">
                        <div class="grid">
                            <div class="grid-item">
                                <div class="label">Provider</div>
                                <div class="value">
                                    @php
                                        $provider = $form->mortgage_preferences['provider'] ?? null;
                                        if (is_array($provider)) {
                                            echo $provider['name'] ?? '—';
                                        } else {
                                            echo $provider ?? '—';
                                        }
                                    @endphp
                                </div>
                            </div>
                            <div class="grid-item">
                                <div class="label">Loan Amount</div>
                                <div class="value">
                                    @php $loanAmount = $form->mortgage_preferences['loan_amount'] ?? null; @endphp
                                    {{ $loanAmount ? '₦' . number_format($loanAmount, 2) : '—' }}
                                </div>
                            </div>
                            <div class="grid-item">
                                <div class="label">Monthly Payment</div>
                                <div class="value">
                                    @php $monthly = $form->mortgage_preferences['monthly_payment'] ?? null; @endphp
                                    {{ $monthly ? '₦' . number_format($monthly, 2) : '—' }}
                                </div>
                            </div>
                            <div class="grid-item">
                                <div class="label">Interest Rate</div>
                                <div class="value">
                                    {{ isset($form->mortgage_preferences['interest_rate']) ? $form->mortgage_preferences['interest_rate'] . '%' : '—' }}
                                </div>
                            </div>
                            <div class="grid-item">
                                <div class="label">Tenure (Years)</div>
                                <div class="value">{{ $form->mortgage_preferences['tenure_years'] ?? '—' }}</div>
                            </div>
                            <div class="grid-item">
                                <div class="label">Status</div>
                                <div class="value">{{ ucfirst($form->mortgage_preferences['status'] ?? 'pending') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Certification & Authorization -->
        <div class="section">
            <h2>Certification & Authorization</h2>
            <div class="certification-text">
                I, <strong>{{ trim(($form->member->user->first_name ?? '') . ' ' . ($form->member->user->last_name ?? '')) }}</strong>, hereby certify that the information provided above is correct.
                I authorize the cooperative to commence deduction from my account to pay for the property of my choice.
            </div>

            <div class="signature-block">
                <div class="signature-box">
                    <div class="title">Applicant Signature</div>
                    <div class="signature-image">
                        @if($signatureSrc)
                            <img src="{{ $signatureSrc }}" alt="Signature">
                        @else
                            <span style="color: #9ca3af; font-size: 9pt;">Signature not provided</span>
                        @endif
                    </div>
                </div>
                <div class="signature-box">
                    <div class="title">Date</div>
                    <div class="value" style="border: none; margin-top: 30px; text-align: center;">
                        {{ optional($form->signed_at ?? $form->created_at)->format('d/m/Y') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer>
            Generated on {{ now()->format('d F Y • h:i A') }} • Expression of Interest Form • Reference: {{ $form->id }}
        </footer>
    </div>
</body>
</html>