@include('errors.layout', [
    'code' => '500',
    'title' => __('Server Error'),
    'heading' => __('Something went wrong'),
    'message' => __('An unexpected error occurred. Please try again later.'),
])
