@include($customerAreaView, array_merge($customerAreaData, [
    'user' => $user,
    'canAccessDashboard' => $canAccessDashboard,
    'artworksUrl' => $artworksUrl,
    'virtualExhibitionUrl' => $virtualExhibitionUrl,
]))
