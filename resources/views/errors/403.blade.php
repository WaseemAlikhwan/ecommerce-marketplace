@include('errors.layout', [
    'code' => '403',
    'title' => __('Forbidden'),
    'heading' => __('Access denied'),
    'message' => __('You do not have permission to view this page.'),
    'secondaryHref' => route('login'),
    'secondaryLabel' => __('Log in'),
])
