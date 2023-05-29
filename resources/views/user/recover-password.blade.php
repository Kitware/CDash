@extends('cdash', [
    'title' => 'Recover Password'
])

@section('main_content')
    @if($warning !== '')
        <div style="color: red;">{{ $warning }}</div>
        <br/>
    @endif
    @if($message !== '')
        <div style="color: green;">{{ $message }}</div>
        <br/>
    @endif

    <!-- Main -->
    <form method="post" action="" name="loginform">
        <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
            <tbody>
            <tr class="table-heading">
                <td width="10%" class="nob"><div align="right"></div></td>
                <td width="90%" class="nob"><div align="left"><b>Enter your email address you registered with CDash.</b></div></td>
            </tr>
            <tr class="table-heading">
                <td width="10%" class="nob"><div align="right"> Email: </div></td>
                <td  width="90%" class="nob"><input class="textbox" name="email" size="40"/></td>
            </tr>
            <tr class="table-heading">
                <td width="10%" class="nob"></td>
                <td width="90%" class="nob"><input type="submit" value="Recover password &gt;&gt;" name="recover" class="textbox"/>
                </td>
            </tr>
            </tbody>
        </table>
    </form>
@endsection
