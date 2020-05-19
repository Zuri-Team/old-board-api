<table>
    <thead>
    <tr>
        <th>Full Name</th>
        <th>Slack Name</th>
        <th>Courses</th>
        <th>Total Points Scored</th>
        <th>Total Obtainable Points</th>
        <th>Percent</th>
    </tr>
    </thead>
    <tbody>
    @foreach($users as $user)
        <tr>
            <td>{{ $user->firstname }}  {{ $user->lastname }}</td>
            <td>{{ $user->username }}</td>
            <td>{{ str_replace('FINAL TASK', '', implode(", ", $user->courses->pluck('name')->all())) }}</td>
            <td>{{ $user->totalScore() }}</td>
            <td>{{ $user->courseTotal() }}</td>
            <td>{{ $user->percentValue() }}</td>
        </tr>
    @endforeach
    </tbody>
</table>