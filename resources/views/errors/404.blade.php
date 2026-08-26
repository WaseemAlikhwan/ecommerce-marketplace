@include('errors.layout', [
    'code' => '404',
    'title' => __('Not Found'),
    'heading' => __('Page not found'),
    'message' => __('This page is missing or no longer available.'),
])
