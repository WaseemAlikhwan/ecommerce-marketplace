@include('errors.layout', [
    'code' => '419',
    'title' => __('Page Expired'),
    'heading' => __('Page expired'),
    'message' => __('Your session expired. Refresh the page and try again.'),
    'secondaryHref' => url()->previous() ?: route('home'),
    'secondaryLabel' => __('Go back'),
])
