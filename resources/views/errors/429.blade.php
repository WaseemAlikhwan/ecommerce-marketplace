@include('errors.layout', [
    'code' => '429',
    'title' => __('Too Many Requests'),
    'heading' => __('Too many requests'),
    'message' => __('Please wait a moment before trying again.'),
])
