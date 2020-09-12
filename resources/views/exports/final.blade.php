<table>
    <thead>
    <tr>
        <th>HNG ID</th>
        <th>First Name</th>
        <th>Last Name</th>
        <th>User Name</th>
        <th>Email Address</th>
        <th>Location</th>
        <th>Gender</th>
        {{-- <th>Courses</th> --}}
        <th>Tracks</th>
        <th>Probation Count</th>
        <th>Probation Reason(s)</th>
        {{-- <th>Total Points Scored</th>
        <th>Total Obtainable Points</th>
        <th>Percent</th> --}}
    </tr>
    </thead>
    <tbody>
    @foreach($users as $user)
        <tr>
            <td>HNG{{ sprintf("%05d", $user->id) }}</td>
            <td>{{ $user->firstname }} </td>
            <td>{{ $user->lastname }}</td>
            <td>{{ $user->username }}</td>
            <td>{{ $user->email }}</td>
            <td>{{ $user->location }}</td>
            <td>{{ ucfirst($user->gender) }}</td>
            {{-- <td>{{ str_replace('FINAL TASK', '', implode(", ", $user->courses->pluck('name')->all())) }}</td> --}}
            <td>{{ str_replace('General', '', implode(", ", $user->tracks->pluck('track_name')->all())) }}</td>
            {{-- <td>{{ implode(", ", $user->tracks->pluck('track_name')->all()) }}</td> --}}

            <td>{{ $user->probationCount() }}</td>
            <td>{{ implode(", ", $user->probationReasons()) }}</td>

            {{-- <td>{{ $user->totalScore() }}</td>
            <td>{{ $user->courseTotal() }}</td>
            <td>{{ $user->percentValue() }}</td> --}}
        </tr>
    @endforeach
    </tbody>
</table>