Canonical: {{ url('.well-known/security.txt') }}
Expires: {{ (now()->month >= 10 ? now()->addMonths(11)->endOfMonth() : now()->endOfYear())->format('c') }}

Contact: https://enflow.nl/contact
Contact: mailto:security@enflow.nl
Contact: tel:+31172700568

Preferred-Languages: en, nl
