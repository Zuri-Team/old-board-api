<table>
    <thead>
    <tr>
        <th>Full Name</th>
        <th>Slack Name</th>
        <th>Email Address</th>
        <th>Slack ID</th>
    </tr>
    </thead>
    <tbody>
    @foreach($users as $user)
        <tr>
            <td>{{ $user->firstname }}  {{ $user->lastname }}</td>
            <td>{{ $user->username }}</td>
            <td>{{ $user->email }}</td>
            <td>{{ $user->slack_id }}</td>
        </tr>
    @endforeach
    </tbody>
</table>