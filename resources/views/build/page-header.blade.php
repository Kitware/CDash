<page-header
    @if(isset($date))
        :date="{{ $date }}"
    @endif
    @if(isset($logo))
        :logo="{{ $logo }}"
    @endif
    @if(isset($projectname))
        :projectname="{{ $projectname }}"
    @endif
    @if(isset($user))
        :user="{{ $user }}"
    @endif
></page-header>
